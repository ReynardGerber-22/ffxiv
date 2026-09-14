<?php

namespace App\Console\Commands;

use App\Services\GatheringSyncService;
use App\Services\XivApiService;
use Illuminate\Console\Command;
use Throwable;

class SyncGatheringRange extends Command
{

    private const SYNC_DELAY_MICROSECONDS = 750_000;
    protected $signature = 'gathering:sync-range
        {job}
        {minLevel}
        {maxLevel}';

    protected $description =
    'Sync gathering data for all raw materials in a crafting level range';

    private array $jobMap = [
        'Carpenter' => 'Woodworking',
        'Blacksmith' => 'Smithing',
        'Armorer' => 'Armorcraft',
        'Goldsmith' => 'Goldsmithing',
        'Leatherworker' => 'Leatherworking',
        'Weaver' => 'Clothcraft',
        'Alchemist' => 'Alchemy',
        'Culinarian' => 'Cooking',
    ];

    public function handle(
        XivApiService $xivApiService,
        GatheringSyncService $gatheringSyncService,
    ): int {
        $job = $this->argument('job');
        $minLevel = (int) $this->argument('minLevel');
        $maxLevel = (int) $this->argument('maxLevel');

        if (!isset($this->jobMap[$job])) {
            $this->error("Unknown crafting job: {$job}");

            return self::FAILURE;
        }

        $stats = [
            'checked' => 0,
            'synced' => 0,
            'fresh' => 0,
            'crystals' => 0,
            'failed' => 0,
        ];

        $this->info(
            "Calculating raw materials for {$job} "
                . "{$minLevel}-{$maxLevel}..."
        );

        $materials = $xivApiService->getExpandedMaterialList(
            $this->jobMap[$job],
            $minLevel,
            $maxLevel
        );

        $itemIds = array_column($materials, 'id');

        $this->info(
            'Found ' . count($itemIds) . ' raw material(s).'
        );

        foreach ($materials as $material) {
            $stats['checked']++;

            if (
                str_contains($material['name'], 'Shard') ||
                str_contains($material['name'], 'Crystal') ||
                str_contains($material['name'], 'Cluster')
            ) {
                $stats['crystals']++;

                $this->line("Skipping crystal: {$material['name']}");
                continue;
            }

            if (!$gatheringSyncService->needsSync($material['id'])) {
                $stats['fresh']++;

                $this->line("Already fresh: {$material['name']}");
                continue;
            }

            $this->line(
                "Syncing {$material['name']} ({$material['id']})..."
            );

            try {
                $gatheringSyncService->sync($material['id']);

                $stats['synced']++;

                usleep(self::SYNC_DELAY_MICROSECONDS);
            } catch (Throwable $exception) {
                $stats['failed']++;

                $this->error(
                    "Failed: {$material['name']} - {$exception->getMessage()}"
                );
            }
        }
        $this->newLine();

        $this->info('Gathering sync complete.');

        $this->table(
            ['Checked', 'Synced', 'Already Fresh', 'Crystals Skipped', 'Failed'],
            [[
                $stats['checked'],
                $stats['synced'],
                $stats['fresh'],
                $stats['crystals'],
                $stats['failed'],
            ]]
        );

        return self::SUCCESS;
    }
}
