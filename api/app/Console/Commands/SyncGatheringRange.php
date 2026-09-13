<?php

namespace App\Console\Commands;

use App\Services\GatheringSyncService;
use App\Services\XivApiService;
use Illuminate\Console\Command;

class SyncGatheringRange extends Command
{
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

            if (
                str_contains($material['name'], 'Shard') ||
                str_contains($material['name'], 'Crystal') ||
                str_contains($material['name'], 'Cluster')
            ) {
                $this->line(
                    "Skipping crystal: {$material['name']}"
                );

                continue;
            }
            
            $this->line(
                "Syncing {$material['name']} ({$material['id']})..."
            );

            $gatheringSyncService->sync($material['id']);
        }

        $this->info('Gathering sync complete.');

        return self::SUCCESS;
    }
}
