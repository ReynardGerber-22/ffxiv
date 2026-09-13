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

        foreach ($itemIds as $itemId) {
            $items[] = $this->sync($itemId);
        }

        return $items;
    }
}
