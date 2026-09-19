<?php

namespace App\Services;

use App\Models\DungeonDrop;

class DungeonDropService
{
    public function enrichMaterials(array $materials): array
    {
        $drops = DungeonDrop::whereIn('item_id', array_column($materials, 'id'))
            ->orderBy('name')->get()->groupBy('item_id');

        return array_map(function (array $material) use ($drops): array {
            $material['dungeons'] = $drops->get($material['id'], collect())
                ->map(fn (DungeonDrop $drop): array => ['id' => $drop->instance_id, 'name' => $drop->name])->all();

            return $material;
        }, $materials);
    }
}
