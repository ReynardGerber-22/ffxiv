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

        $bnpcNameIds = $drops
            ->pluck('bnpc_name_id')
            ->unique();

        $spawns = MobSpawn::query()
            ->whereIn('bnpc_name_id', $bnpcNameIds)
            ->get()
            ->groupBy('bnpc_name_id');

        $mobs = [];

        foreach ($drops as $drop) {
            $mobKey = $drop->mob_name;

            if (!isset($mobs[$mobKey])) {
                $mobs[$mobKey] = [
                    'mob' => $drop->mob_name,
                    'territories' => [],
                ];
            }

            foreach ($spawns->get($drop->bnpc_name_id, collect()) as $spawn) {
                $territoryKey = $spawn->territory_type_id;

                if (!isset($mobs[$mobKey]['territories'][$territoryKey])) {
                    $mobs[$mobKey]['territories'][$territoryKey] = [
                        'territory' => $spawn->territory_name,
                        'locations' => [],
                    ];
                }

                $mobs[$mobKey]['territories'][$territoryKey]['locations'][] = [
                    'x' => (float) $spawn->x,
                    'y' => (float) $spawn->y,
                ];
            }
        }

        return array_values(
            array_map(function (array $mob) {
                $mob['territories'] = array_values($mob['territories']);

                return $mob;
            }, $mobs)
        );
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
            $mobs = [];

            foreach ($drops->get($material['id'], collect()) as $drop) {
                $mobKey = $drop->mob_name;

                if (!isset($mobs[$mobKey])) {
                    $mobs[$mobKey] = [
                        'mob' => $drop->mob_name,
                        'territories' => [],
                    ];
                }

                foreach ($spawns->get($drop->bnpc_name_id, collect()) as $spawn) {
                    $territoryKey = $spawn->territory_type_id;

                    if (!isset($mobs[$mobKey]['territories'][$territoryKey])) {
                        $mobs[$mobKey]['territories'][$territoryKey] = [
                            'territory' => $spawn->territory_name,
                            'locations' => [],
                        ];
                    }

                    $mobs[$mobKey]['territories'][$territoryKey]['locations'][] = [
                        'x' => (float) $spawn->x,
                        'y' => (float) $spawn->y,
                    ];
                }
            }

            $material['mobDrops'] = array_values(
                array_map(function (array $mob) {
                    $mob['territories'] = array_values($mob['territories']);

                    return $mob;
                }, $mobs)
            );

            return $material;
        }, $materials);
    }
}
