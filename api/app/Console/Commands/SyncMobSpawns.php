<?php

namespace App\Console\Commands;

use App\Services\MobSpawnSyncService;
use Illuminate\Console\Command;

class SyncMobSpawns extends Command
{
    protected $signature = 'xivapi:sync-mob-spawns';

    protected $description = 'Sync monster spawn locations from LuminaSupplemental';

    public function handle(MobSpawnSyncService $service): int
    {
        $count = $service->sync();

        $this->info("Synced {$count} mob spawn locations.");

        return self::SUCCESS;
    }
}