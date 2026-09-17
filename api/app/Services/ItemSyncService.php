<?php

namespace App\Services;

use App\Models\Item;

class ItemSyncService
{
    public function __construct(
        private XivApiService $xivApiService
    ) {}

    public function sync(int $itemId): Item
    {
        $data = $this->xivApiService->getItem($itemId);

        return $this->store($itemId, $data);
    }

    private function store(int $itemId, array $data): Item
    {
        return Item::updateOrCreate(
            ['id' => $itemId],
            [
                'name' => $data['fields']['Name'] ?? '',
            ]
        );
    }

    public function syncMany(array $itemIds): array
    {
        $items = [];

        foreach (array_chunk(array_unique($itemIds), 100) as $chunk) {
            $rows = collect($this->xivApiService->getItems($chunk))->keyBy('row_id');
            foreach ($chunk as $itemId) {
                if (! isset($rows[$itemId]) || empty($rows[$itemId]['fields']['Name'])) {
                    throw new \RuntimeException("XIVAPI returned no item metadata for {$itemId}.");
                }
                $items[$itemId] = $this->store($itemId, $rows[$itemId]);
            }
        }

        return array_map(fn ($id) => $items[$id], $itemIds);
    }
}
