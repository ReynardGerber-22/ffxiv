<?php

namespace App\Console\Commands;

use App\Services\MobEnrichmentSyncService;
use Illuminate\Console\Command;

class SyncMobEnrichment extends Command
{
    protected $signature = 'xivapi:sync-mob-enrichment';

    protected $description = 'Enrich mob drop and spawn data with XIVAPI data';

    public function handle(MobEnrichmentSyncService $syncService): int
    {
        $this->info('Syncing mob names...');

        $mobCount = $syncService->syncMobNames();

        $this->info("Enriched {$mobCount} mob names.");

        $this->info('Syncing territory names...');

        $territoryCount = $syncService->syncTerritoryNames();

        $this->info("Enriched {$territoryCount} territory names.");

        return self::SUCCESS;
    }
}
