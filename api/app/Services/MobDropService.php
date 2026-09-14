<?php

namespace App\Services;

use App\Models\MobDrop;
use App\Models\MobSpawn;

class MobDropService
{
    public function getLocationsForItem(int $itemId): array
    {
        $drops = MobDrop::query()
            ->where('item_id', $itemId)
            ->get();

        $locations = [];

        foreach ($drops as $drop) {
            $spawns = MobSpawn::query()
                ->where('bnpc_name_id', $drop->bnpc_name_id)
                ->get();

            foreach ($spawns as $spawn) {
                $key = "{$drop->mob_name}-{$spawn->territory_type_id}";
                if (!isset($locations[$key])) {
                    $locations[$key] = [
                        'mob' => $drop->mob_name,
                        'territory' => $spawn->territory_name,
                        'locations' => [],
                    ];
                }

                $locations[$key]['locations'][] = [
                    'x' => (float) $spawn->x,
                    'y' => (float) $spawn->y,
                ];
            }
        }

        return array_values($locations);
    }
    
    public function enrichMaterials(array $materials): array
    {
        $itemIds = array_column($materials, 'id');

        $drops = MobDrop::query()
            ->whereIn('item_id', $itemIds)
            ->get()
            ->groupBy('item_id');

        $bnpcNameIds = $drops
            ->flatten()
            ->pluck('bnpc_name_id')
            ->unique()
            ->values();

        $spawns = MobSpawn::query()
            ->whereIn('bnpc_name_id', $bnpcNameIds)
            ->get()
            ->groupBy('bnpc_name_id');

        return array_map(function (array $material) use ($drops, $spawns) {
            $locations = [];

            foreach ($drops->get($material['id'], collect()) as $drop) {
                foreach ($spawns->get($drop->bnpc_name_id, collect()) as $spawn) {
                    $key = "{$drop->mob_name}-{$spawn->territory_type_id}";

                    if (!isset($locations[$key])) {
                        $locations[$key] = [
                            'mob' => $drop->mob_name,
                            'territory' => $spawn->territory_name,
                            'locations' => [],
                        ];
                    }

                    $locations[$key]['locations'][] = [
                        'x' => (float) $spawn->x,
                        'y' => (float) $spawn->y,
                    ];
                }
            }

            $material['mobDrops'] = array_values($locations);

            return $material;
        }, $materials);
    }
}
