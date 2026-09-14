<?php

namespace App\Services;

use App\Models\MobDrop;
use App\Models\MobSpawn;

class MobEnrichmentSyncService
{
    public function __construct(
        private MobEnrichmentService $enrichmentService
    ) {}

    public function syncMobNames(): int
    {
        $bnpcNameIds = MobDrop::query()
            ->whereNull('mob_name')
            ->distinct()
            ->pluck('bnpc_name_id');

        $count = 0;

        foreach ($bnpcNameIds as $bnpcNameId) {
            $mobName = $this->enrichmentService->getMobName($bnpcNameId);

            if (!$mobName) {
                continue;
            }

            MobDrop::where('bnpc_name_id', $bnpcNameId)
                ->update([
                    'mob_name' => $mobName,
                ]);

            $count++;
        }

        return $count;
    }

    public function syncTerritoryNames(): int
    {
        $territoryIds = MobSpawn::query()
            ->whereNull('territory_name')
            ->distinct()
            ->pluck('territory_type_id');

        $count = 0;

        foreach ($territoryIds as $territoryId) {
            $territoryName = $this->enrichmentService
                ->getTerritoryName($territoryId);

            if (!$territoryName) {
                continue;
            }

            MobSpawn::where('territory_type_id', $territoryId)
                ->update([
                    'territory_name' => $territoryName,
                ]);

            $count++;
        }

        return $count;
    }
}
