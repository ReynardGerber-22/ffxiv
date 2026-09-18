<?php

namespace App\Services;

use App\Models\GilShopItem;

class VendorService
{
    public function enrichMaterials(array $materials): array
    {
        $offers = GilShopItem::with('shop.vendors.locations')
            ->whereIn('item_id', array_column($materials, 'id'))
            ->where('is_hq', false)->where('price', '>', 0)
            ->get()->groupBy('item_id');

        return array_map(function ($material) use ($offers) {
            $vendors = [];
            foreach ($offers->get($material['id'], collect()) as $offer) {
                foreach ($offer->shop->vendors as $npc) {
                    $name = strtolower(trim($npc->name));
                    if ($name === '' || $name === 'allagan resupply node' || str_ends_with($name, 'material supplier')) {
                        continue;
                    }
                    foreach ($npc->locations->isEmpty() ? [null] : $npc->locations as $location) {
                        $entry = [
                            'id' => $npc->id, 'name' => $npc->name, 'title' => $npc->title,
                            'price' => $offer->price, 'territory' => $location?->territory,
                            'area' => $location?->area, 'mapId' => $location?->map_id,
                            'x' => $location ? round($location->x, 1) : null,
                            'y' => $location ? round($location->y, 1) : null,
                            'locationResolved' => $location !== null,
                        ];
                        // Merge identical cards across menus; retain different NPC IDs/prices/locations.
                        $vendors[json_encode($entry, JSON_THROW_ON_ERROR)] = $entry;
                    }
                }
            }
            $vendors = array_values($vendors);
            usort($vendors, fn ($a, $b) => [$a['locationResolved'] ? 0 : 1, $a['price'], $a['name'], $a['id']]
                <=> [$b['locationResolved'] ? 0 : 1, $b['price'], $b['name'], $b['id']]);
            $material['vendors'] = $vendors;

            return $material;
        }, $materials);
    }
}
