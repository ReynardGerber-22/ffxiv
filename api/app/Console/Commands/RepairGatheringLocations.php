<?php

namespace App\Console\Commands;

use App\Models\GatheringNode;
use App\Models\Item;
use App\Services\GatheringSyncService;
use Illuminate\Console\Command;
use Throwable;

class RepairGatheringLocations extends Command
{
    protected $signature = 'gathering:repair-locations';

    protected $description =
        'Resync items connected to gathering nodes with missing location data';

    public function handle(
        GatheringSyncService $gatheringSyncService
    ): int {
        $badNodes = GatheringNode::query()
            ->where(function ($query) {
                $query
                    ->where('area_name', '')
                    ->orWhere('territory_name', '')
                    ->orWhere('map_id', 0);
            })
            ->with('items')
            ->get();

        if ($badNodes->isEmpty()) {
            $this->info('No gathering nodes need location repair.');

            return self::SUCCESS;
        }

        $itemIds = $badNodes
            ->flatMap(fn (GatheringNode $node) => $node->items->pluck('id'))
            ->unique()
            ->values();

        $this->info(
            "Found {$badNodes->count()} bad gathering node(s)."
        );

        $this->info(
            "Found {$itemIds->count()} affected item(s)."
        );

        $stats = [
            'repaired' => 0,
            'failed' => 0,
        ];

        foreach ($itemIds as $itemId) {
            $item = Item::find($itemId);

            if ($item === null) {
                continue;
            }

            $this->line(
                "Repairing {$item->name} ({$item->id})..."
            );

            try {
                $item->update([
                    'gathering_checked_at' => null,
                ]);

                $gatheringSyncService->sync($item->id);

                $stats['repaired']++;
            } catch (Throwable $exception) {
                $stats['failed']++;

                $this->error(
                    "Failed: {$item->name} - {$exception->getMessage()}"
                );
            }
        }

        $this->newLine();

        $this->table(
            ['Affected Items', 'Repaired', 'Failed'],
            [[
                $itemIds->count(),
                $stats['repaired'],
                $stats['failed'],
            ]]
        );

        return self::SUCCESS;
    }
}