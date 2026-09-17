<?php

namespace App\Services;

use App\Models\FishingSpot;
use App\Models\Item;

class FishingService
{
    public function toFishingInfo(FishingSpot $spot, array $baits = []): array
    {
        $x = $spot->getMapX();
        $y = $spot->getMapY();

        return [
            'baits' => $baits,
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

        $items = Item::with(['fishingSpots', 'fishingBaits.baitItem'])
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
                    fn (FishingSpot $spot) => $this->toFishingInfo($spot, $item->fishingBaits
                        ->where('fishing_spot_id', $spot->id)
                        ->unique('bait_item_id')
                        ->map(fn ($recommendation) => [
                            'id' => $recommendation->baitItem->id,
                            'name' => $recommendation->baitItem->name,
                        ])->values()->all())
                )
                ->values()
                ->all();

            return $material;
        }, $materials);
    }
}
