<?php

namespace App\Console\Commands;

use App\Services\GatheringSyncService;
use Illuminate\Console\Command;

class SyncGatheringData extends Command
{
    protected $signature = 'gathering:sync {itemIds*}';

    protected $description = 'Sync gathering data for one or more FFXIV item IDs';

    public function handle(GatheringSyncService $gatheringSyncService): int
    {
        $itemIds = array_map(
            'intval',
            $this->argument('itemIds')
        );

        $this->info(
            'Syncing gathering data for '
            . count($itemIds)
            . ' item(s)...'
        );

        foreach ($itemIds as $itemId) {
            $this->line("Syncing item {$itemId}...");

            $gatheringSyncService->sync($itemId);
        }

        $this->info('Gathering sync complete.');

        return self::SUCCESS;
    }
}