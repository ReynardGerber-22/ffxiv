<?php

namespace App\Services;

use App\Models\Item;
use App\Models\FishingSpot;

class FishingSyncService
{
    public function __construct(
        private XivApiService $xivApiService
    ) {}

    public function sync(int $itemId): void
    {
        $item = Item::find($itemId);

        if (!$item) {
            return;
        }

        if ($item->gatheringNodes()->exists()) {
            $item->update([
                'fishing_checked_at' => now(),
            ]);

            return;
        }

        if ($item->mobDrops()->exists()) {
            $item->update([
                'fishing_checked_at' => now(),
            ]);

            return;
        }

        $spots = $this->xivApiService
            ->findFishingSpotsByItemId($itemId);

        foreach ($spots as $spot) {
            $spotId = $spot['row_id'];

            $spotData = $this->xivApiService
                ->getFishingSpot($spotId);

            $mapId =
                $spotData['fields']['TerritoryType']['fields']['Map@as(raw)']
                ?? null;

            $mapData = $mapId
                ? $this->xivApiService->getMap($mapId)
                : null;

            $fishingSpot = FishingSpot::updateOrCreate(
                ['id' => $spotId],
                [
                    'name' =>
                    $spotData['fields']['PlaceName']['fields']['Name']
                        ?? '',

                    'fishing_level' =>
                    $spotData['fields']['GatheringLevel']
                        ?? 0,

                    'territory_name' =>
                    $spotData['fields']['TerritoryType']['fields']['PlaceName']['fields']['Name']
                        ?? '',

                    'map_id' => $mapId,

                    'raw_x' => $spotData['fields']['X'] ?? null,
                    'raw_z' => $spotData['fields']['Z'] ?? null,
                    'radius' => $spotData['fields']['Radius'] ?? null,

                    'map_size_factor' =>
                    $mapData['fields']['SizeFactor']
                        ?? null,

                    'map_offset_x' =>
                    $mapData['fields']['OffsetX']
                        ?? null,

                    'map_offset_y' =>
                    $mapData['fields']['OffsetY']
                        ?? null,
                ]
            );

            $item->fishingSpots()->syncWithoutDetaching([
                $fishingSpot->id,
            ]);
        }

        $item->update([
            'fishing_checked_at' => now(),
        ]);
    }

    public function needsSync(int $itemId): bool
    {
        $item = Item::find($itemId);

        if ($item === null) {
            return true;
        }

        if ($item->fishing_checked_at === null) {
            return true;
        }

        return $item->fishing_checked_at->lt(
            now()->subDays(30)
        );
    }
}
