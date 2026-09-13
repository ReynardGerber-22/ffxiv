<?php

namespace App\Services;

use App\Models\GatheringNode;
use App\Models\Item;

class GatheringService
{
    public function toGatheringInfo(GatheringNode $node): array
    {
        return [
            'type' => $node->gathering_type,
            'level' => $node->gathering_level,
            'area' => $node->area_name,
            'territory' => $node->territory_name,
            'x' => round($node->getMapX(), 1),
            'y' => round($node->getMapY(), 1),
        ];
    }

    public function enrichMaterial(array $material): array
    {
        $item = Item::with('gatheringNodes')
            ->find($material['id']);

        if ($item === null) {
            return $material;
        }

        $material['gathering'] = $item->gatheringNodes
            ->map(fn(GatheringNode $node) => $this->toGatheringInfo($node))
            ->values()
            ->all();

        return $material;
    }

    public function enrichMaterials(array $materials): array
    {
        $itemIds = array_column($materials, 'id');

        $items = Item::with('gatheringNodes')
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        return array_map(function (array $material) use ($items) {
            $item = $items->get($material['id']);

            if ($item === null) {
                return $material;
            }

            $material['gathering'] = $item->gatheringNodes
                ->map(fn(GatheringNode $node) => $this->toGatheringInfo($node))
                ->values()
                ->all();

            return $material;
        }, $materials);
    }
}
