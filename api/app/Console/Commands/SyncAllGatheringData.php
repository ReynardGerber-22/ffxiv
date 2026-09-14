<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncAllGatheringData extends Command
{
    protected $signature = 'gathering:sync-all';

    protected $description =
        'Sync gathering data for all crafting jobs and level ranges';

    private array $jobs = [
        'Carpenter',
        'Blacksmith',
        'Armorer',
        'Goldsmith',
        'Leatherworker',
        'Weaver',
        'Alchemist',
        'Culinarian',
    ];

    private array $ranges = [
        [1, 20],
        [21, 40],
        [41, 60],
        [61, 80],
        [81, 100],
    ];

    public function handle(): int
    {
        foreach ($this->jobs as $job) {
            $this->newLine();
            $this->info("========== {$job} ==========");

            foreach ($this->ranges as [$minLevel, $maxLevel]) {
                $this->newLine();

                $this->info(
                    "{$job}: levels {$minLevel}-{$maxLevel}"
                );

                $exitCode = $this->call(
                    'gathering:sync-range',
                    [
                        'job' => $job,
                        'minLevel' => $minLevel,
                        'maxLevel' => $maxLevel,
                    ]
                );

                if ($exitCode !== self::SUCCESS) {
                    $this->warn(
                        "Range failed: {$job} {$minLevel}-{$maxLevel}"
                    );
                }
            }
        }

        $this->newLine();
        $this->info('All gathering syncs complete.');

        return self::SUCCESS;
    }
}