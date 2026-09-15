<?php

namespace App\Services;

use App\Models\FishingSpot;
use App\Models\Item;

class FishingService
{
    public function toFishingInfo(FishingSpot $spot): array
    {
        $x = $spot->getMapX();
        $y = $spot->getMapY();

        return [
            'level' => $spot->fishing_level,
            'spot' => $spot->name,
            'territory' => $spot->territory_name,
            'x' => $x !== null ? round($x, 1) : null,
            'y' => $y !== null ? round($y, 1) : null,
        ];
    }
    public function enrichMaterials(array $materials): array
    {
        $itemIds = array_column($materials, 'id');

        $items = Item::with('fishingSpots')
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        return array_map(function (array $material) use ($items) {
            $item = $items->get($material['id']);

            if ($item === null) {
                return $material;
            }

            $material['fishing'] = $item->fishingSpots
                ->map(
                    fn(FishingSpot $spot) =>
                    $this->toFishingInfo($spot)
                )
                ->values()
                ->all();

            return $material;
        }, $materials);
    }
}
