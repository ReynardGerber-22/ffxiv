<?php

namespace App\Services;

use App\Models\GatheringNode;
use App\Models\GatheringNodeItem;
use App\Models\Item;
use Illuminate\Http\Client\RequestException;

class GatheringSyncService
{
    public function __construct(
        private XivApiService $xivApiService,
        private ItemSyncService $itemSyncService,
    ) {}

    public function sync(int $itemId): void
    {
        $item = $this->itemSyncService->sync($itemId);

        $gatheringItems = $this->xivApiService
            ->findGatheringItemsByItemId($itemId);

        foreach ($gatheringItems['results'] ?? [] as $gatheringItemRow) {
            $gatheringItemId = $gatheringItemRow['row_id'];

            $gatheringItem = $this->xivApiService
                ->getGatheringItem($gatheringItemId);

            $pointBases = $this->xivApiService
                ->findGatheringPointBasesByGatheringItemId($gatheringItemId);

            foreach ($pointBases['results'] ?? [] as $pointBaseRow) {
                $baseId = $pointBaseRow['row_id'];

                $this->syncGatheringNode(
                    $item,
                    $gatheringItemId,
                    $gatheringItem,
                    $pointBaseRow,
                    $baseId,
                );
            }
        }

        $item->update([
            'gathering_checked_at' => now(),
        ]);
    }

    private function syncGatheringNode(
        Item $item,
        int $gatheringItemId,
        array $gatheringItem,
        array $pointBaseRow,
        int $baseId,
    ): void {
        $gatheringPoints = $this->xivApiService
            ->findGatheringPointsByBaseId($baseId);

        $point = $this->selectBestGatheringPoint(
            $gatheringPoints['results'] ?? []
        );

        if ($point === null) {
            return;
        }

        $fields = $point['fields'];

        $areaName =
            $fields['PlaceName']['fields']['Name'] ?? '';

        $territoryName =
            $fields['TerritoryType']['fields']['PlaceName']['fields']['Name']
            ?? '';

        $mapId =
            $fields['TerritoryType']['fields']['Map@as(raw)'] ?? 0;

        if ($mapId === 0) {
            return;
        }

        $map = $this->xivApiService->getMap($mapId);

        try {
            $exportedPoint = $this->xivApiService
                ->getExportedGatheringPoint($baseId);
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                return;
            }

            throw $exception;
        }

        $node = GatheringNode::updateOrCreate(
            ['id' => $baseId],
            [
                'gathering_type' =>
                    $pointBaseRow['fields']['GatheringType']['fields']['Name']
                    ?? '',

                'gathering_level' =>
                    $pointBaseRow['fields']['GatheringLevel'] ?? 0,

                'area_name' => $areaName,

                'territory_name' => $territoryName,

                'map_id' => $mapId,

                'raw_x' =>
                    $exportedPoint['fields']['X'] ?? null,

                'raw_y' =>
                    $exportedPoint['fields']['Y'] ?? null,

                'radius' =>
                    $exportedPoint['fields']['Radius'] ?? null,

                'map_size_factor' =>
                    $map['fields']['SizeFactor'] ?? 100,

                'map_offset_x' =>
                    $map['fields']['OffsetX'] ?? 0,

                'map_offset_y' =>
                    $map['fields']['OffsetY'] ?? 0,
            ]
        );

        GatheringNodeItem::updateOrCreate(
            [
                'gathering_node_id' => $node->id,
                'item_id' => $item->id,
            ],
            [
                'gathering_item_id' => $gatheringItemId,
            ]
        );
    }

    public function syncMany(array $itemIds): void
    {
        foreach ($itemIds as $itemId) {
            $this->sync($itemId);
        }
    }

    public function syncMissing(array $itemIds): void
    {
        $existingIds = Item::query()
            ->whereIn('id', $itemIds)
            ->pluck('id')
            ->all();

        $missingIds = array_diff($itemIds, $existingIds);

        foreach ($missingIds as $itemId) {
            $this->sync($itemId);
        }
    }

    public function needsSync(int $itemId): bool
    {
        $item = Item::find($itemId);

        if ($item === null) {
            return true;
        }

        if ($item->gathering_checked_at === null) {
            return true;
        }

        return $item->gathering_checked_at->lt(
            now()->subDays(30)
        );
    }

    private function selectBestGatheringPoint(array $points): ?array
    {
        $validPoint = collect($points)
            ->first(function (array $point) {
                $area =
                    $point['fields']['PlaceName']['fields']['Name']
                    ?? '';

                $territory =
                    $point['fields']['TerritoryType']['fields']['PlaceName']['fields']['Name']
                    ?? '';

                $mapId =
                    $point['fields']['TerritoryType']['fields']['Map@as(raw)']
                    ?? 0;

                return $area !== ''
                    && $territory !== ''
                    && $mapId !== 0;
            });

        return $validPoint;
    }
}