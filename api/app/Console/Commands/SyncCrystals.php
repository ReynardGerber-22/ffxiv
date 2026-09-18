<?php

namespace App\Console\Commands;

use App\Services\CrystalGatheringSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncCrystals extends Command
{
    protected $signature = 'crystals:sync
        {--item= : Exact English item name, e.g. Fire Shard}
        {--type= : shard, crystal, or cluster}';

    protected $description = 'Incrementally sync gathering locations for elemental crafting crystals';

    public function handle(CrystalGatheringSyncService $service): int
    {
        $type = $this->option('type');
        $item = $this->option('item');
        if ($type !== null && ! in_array($type, ['shard', 'crystal', 'cluster'], true)) {
            $this->error('--type must be shard, crystal, or cluster.');

            return self::FAILURE;
        }
        $names = $service->itemNames($type);
        if ($item !== null) {
            if (! in_array($item, $names, true)) {
                $this->error('--item must be an exact elemental crystal name matching --type, if supplied.');

                return self::FAILURE;
            }
            $names = [$item];
        }
        $failed = 0;
        foreach ($names as $index => $name) {
            $this->info('['.($index + 1).'/'.count($names)."] {$name}");
            try {
                $stats = $service->sync($name, function (array $stats): void {
                    $this->line("  Page {$stats['pages']}: {$stats['records']} gathering records; {$stats['nodes']} nodes and {$stats['relationships']} relationships upserted; {$stats['skipped']} unavailable positions.");
                });
                if ($stats['skipped'] > 0) {
                    $this->warn('  Unavailable positions skipped; existing data and freshness timestamp preserved.');
                }
                $this->line('  Complete. Memory: '.round(memory_get_usage(true) / 1048576, 1).' MiB');
            } catch (Throwable $exception) {
                $failed++;
                $this->error("  Failed {$name}: {$exception->getMessage()}");
                $this->warn('  Completed writes are preserved. Rerun this item to retry.');
            }
        }
        $this->info('Finished: '.(count($names) - $failed)." completed, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
