<?php

namespace App\Services;

use App\Models\GatheringNode;
use App\Models\GatheringNodeItem;
use App\Models\Item;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CrystalGatheringSyncService
{
    public function __construct(
        private XivApiService $xivApiService,
        private ItemSyncService $itemSyncService,
    ) {}

    public function itemNames(?string $type = null): array
    {
        $names = [];
        foreach (['shard', 'crystal', 'cluster'] as $category) {
            if ($type !== null && $category !== $type) {
                continue;
            }
            foreach (['Fire', 'Ice', 'Wind', 'Earth', 'Lightning', 'Water'] as $element) {
                $names[] = $element.' '.ucfirst($category);
            }
        }

        return $names;
    }

    private function resolveItem(string $name): Item
    {
        $items = Item::where('name', $name)->limit(2)->get();
        if ($items->count() === 1) {
            return $items->first();
        }
        if ($items->count() > 1) {
            throw new RuntimeException("Multiple local items named {$name}.");
        }

        $id = null;
        foreach ($this->xivApiService->searchPages('Item', 'Name="'.$name.'"', 'Name') as $rows) {
            foreach ($rows as $row) {
                if (($row['fields']['Name'] ?? '') !== $name) {
                    continue;
                }
                if ($id !== null && $id !== $row['row_id']) {
                    throw new RuntimeException("Multiple XIVAPI items named {$name}.");
                }
                $id = $row['row_id'];
            }
        }
        if ($id === null) {
            throw new RuntimeException("Could not resolve {$name} through XIVAPI.");
        }

        return $this->itemSyncService->syncMany([$id])[0];
    }

    /**
     * Only one search page and its map/position batches are retained at a time.
     * The seen set holds base IDs only, and is released after each item.
     *
     * @return array{records: int, nodes: int, relationships: int, skipped: int, pages: int}
     */
    public function sync(string $name, ?callable $progress = null): array
    {
        if (! in_array($name, $this->itemNames(), true)) {
            throw new RuntimeException("Not an elemental crafting crystal: {$name}");
        }
        $item = $this->resolveItem($name);
        $stats = ['records' => 0, 'nodes' => 0, 'relationships' => 0, 'skipped' => 0, 'pages' => 0];
        $seen = [];
        $fields = implode(',', [
            'GatheringPointBase.GatheringLevel', 'GatheringPointBase.GatheringType.Name',
            'GatheringPointBase.Item[].Item@as(raw)', 'PlaceName.Name',
            'TerritoryType.PlaceName.Name', 'TerritoryType.Map@as(raw)',
        ]);
        $query = '+GatheringPointBase.Item[].Item='.$item->id.' +TerritoryType.Map>0 +PlaceName>0';
        foreach ($this->xivApiService->searchPages('GatheringPoint', $query, $fields) as $points) {
            $stats['pages']++;
            $stats['records'] += count($points);
            $candidates = [];
            foreach ($points as $point) {
                $data = $point['fields'];
                $base = $data['GatheringPointBase'];
                $baseId = (int) $base['row_id'];
                if (isset($seen[$baseId]) || isset($candidates[$baseId])) {
                    continue;
                }
                $area = trim($data['PlaceName']['fields']['Name'] ?? '');
                $territory = trim($data['TerritoryType']['fields']['PlaceName']['fields']['Name'] ?? '');
                $mapId = (int) ($data['TerritoryType']['fields']['Map@as(raw)'] ?? 0);
                if ($area === '' || $territory === '' || $mapId === 0) {
                    continue;
                }
                $gatheringItemId = null;
                foreach ($base['fields']['Item'] ?? [] as $entry) {
                    if (($entry['sheet'] ?? '') === 'GatheringItem' && (int) ($entry['fields']['Item@as(raw)'] ?? 0) === $item->id) {
                        $gatheringItemId = (int) $entry['row_id'];
                        break;
                    }
                }
                if ($gatheringItemId === null) {
                    throw new RuntimeException("Missing GatheringItem relationship for base {$baseId}.");
                }
                $candidates[$baseId] = [
                    'gathering_item_id' => $gatheringItemId,
                    'gathering_type' => $base['fields']['GatheringType']['fields']['Name'],
                    'gathering_level' => $base['fields']['GatheringLevel'],
                    'area_name' => $area, 'territory_name' => $territory, 'map_id' => $mapId,
                ];
            }
            if ($candidates !== []) {
                $positions = $this->exportedPoints(array_keys($candidates));
                $maps = $this->xivApiService->getSheetRows('Map', array_column($candidates, 'map_id'), 'SizeFactor,OffsetX,OffsetY');
                foreach ($candidates as $baseId => $candidate) {
                    $position = $positions[$baseId]['fields'] ?? [];
                    $map = $maps[$candidate['map_id']]['fields'] ?? [];
                    if (! is_numeric($position['X'] ?? null) || ! is_numeric($position['Y'] ?? null) || ($map['SizeFactor'] ?? 0) <= 0) {
                        $seen[$baseId] = true;
                        $stats['skipped']++;

                        continue;
                    }
                    $gatheringItemId = $candidate['gathering_item_id'];
                    unset($candidate['gathering_item_id']);
                    DB::transaction(function () use ($baseId, $candidate, $position, $map, $item, $gatheringItemId): void {
                        GatheringNode::updateOrCreate(['id' => $baseId], $candidate + [
                            'raw_x' => $position['X'], 'raw_y' => $position['Y'], 'radius' => $position['Radius'] ?? null,
                            'map_size_factor' => $map['SizeFactor'], 'map_offset_x' => $map['OffsetX'] ?? 0, 'map_offset_y' => $map['OffsetY'] ?? 0,
                        ]);
                        GatheringNodeItem::updateOrCreate(
                            ['gathering_node_id' => $baseId, 'item_id' => $item->id],
                            ['gathering_item_id' => $gatheringItemId],
                        );
                    });
                    $seen[$baseId] = true;
                    $stats['nodes']++;
                    $stats['relationships']++;
                }
            }
            if ($progress !== null) {
                $progress($stats);
            }
            unset($candidates, $positions, $maps);
        }
        if ($stats['skipped'] === 0) {
            $item->update(['gathering_checked_at' => now()]);
        }

        return $stats;
    }

    /** Missing exported rows cause a whole batch to return 404; isolate those rows. */
    private function exportedPoints(array $ids): array
    {
        try {
            return $this->xivApiService->getSheetRows('ExportedGatheringPoint', $ids, 'X,Y,Radius');
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 404) {
                throw $exception;
            }
            if (count($ids) === 1) {
                return [];
            }
            $halves = array_chunk($ids, (int) ceil(count($ids) / 2));

            return $this->exportedPoints($halves[0]) + $this->exportedPoints($halves[1]);
        }
    }
}
