<?php

namespace App\Console\Commands;

use App\Services\MobDropSyncService;
use Illuminate\Console\Command;

class SyncMobDrops extends Command
{
    protected $signature = 'xivapi:sync-mob-drops';

    protected $description = 'Sync monster drop relationships from LuminaSupplemental';

    public function handle(MobDropSyncService $service): int
    {
        $count = $service->sync();

        $this->info("Synced {$count} mob drop relationships.");

        return self::SUCCESS;
    }
}