<?php

namespace App\Services;

use App\Models\GilShop;
use App\Models\GilShopItem;
use App\Models\Item;
use App\Models\VendorLocation;
use App\Models\VendorNpc;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VendorSyncService
{
    public function __construct(
        private XivApiService $xiv,
        private ItemSyncService $items,
        private VendorShopResolver $resolver,
        private VendorLocationService $locations,
    ) {}

    public function sync(array $itemIds): array
    {
        $itemIds = array_values(array_unique($itemIds));
        if ($itemIds === []) {
            return ['shops' => 0, 'offers' => 0, 'vendors' => 0, 'locations' => 0];
        }
        $offers = [];
        foreach (array_chunk($itemIds, 40) as $ids) {
            $query = '+IsHQ=false +('.implode(' ', array_map(fn ($id) => 'Item='.$id, $ids)).')';
            $fields = 'Item.Name,Item.PriceMid,IsHQ,QuestRequired@as(raw),AchievementRequired@as(raw),StateRequired';
            foreach ($this->xiv->searchAll('GilShopItem', $query, $fields) as $row) {
                $f = $row['fields'];
                if (($f['IsHQ'] ?? true) !== false || ($f['Item']['fields']['PriceMid'] ?? 0) <= 0
                    || ! in_array($f['Item']['row_id'] ?? 0, $itemIds)) {
                    continue;
                }
                $offers[$row['row_id'].':'.$row['subrow_id']] = $row;
            }
        }
        $shopIds = array_values(array_unique(array_column($offers, 'row_id')));
        $shops = $this->xiv->getSheetRows('GilShop', $shopIds, 'Name,Quest@as(raw),FestivalId,FestivalPhase');
        if (count($shops) !== count($shopIds)) {
            throw new RuntimeException('Incomplete GilShop metadata; keeping previous vendor data.');
        }
        $links = $this->resolver->resolve($shopIds);
        $npcIds = array_keys($links);
        $npcs = $this->xiv->getSheetRows('ENpcResident', $npcIds, 'Singular,Title');
        if (count($npcs) !== count($npcIds)) {
            throw new RuntimeException('Incomplete NPC metadata; keeping previous vendor data.');
        }
        $npcs = array_filter($npcs, function ($row): bool {
            $name = strtolower(trim($row['fields']['Singular'] ?? ''));
            return $name !== '' && $name !== 'allagan resupply node' && ! str_ends_with($name, 'material supplier');
        });
        $validNpcIds = array_keys($npcs);
        $links = array_intersect_key($links, array_flip($validNpcIds));
        $locations = $this->locations->fetch($validNpcIds);
        $offeredItems = array_unique(array_map(fn ($r) => $r['fields']['Item']['row_id'], $offers));
        $existing = Item::whereIn('id', $offeredItems)->pluck('id')->all();
        $this->items->syncMany(array_values(array_diff($offeredItems, $existing)));

        // External requests finish first. A failed fetch must not destroy a good snapshot.
        DB::transaction(function () use ($itemIds, $shops, $offers, $npcs, $links, $locations) {
            VendorNpc::whereRaw("TRIM(name) = '' OR LOWER(TRIM(name)) = 'allagan resupply node' OR LOWER(TRIM(name)) LIKE '% material supplier'")->delete();
            foreach ($shops as $id => $row) {
                GilShop::updateOrCreate(['id' => $id], [
                    'name' => $row['fields']['Name'] ?: null, 'source_data' => $row['fields'],
                ]);
            }
            $offerIds = [];
            foreach ($offers as $row) {
                $f = $row['fields'];
                $offerIds[] = GilShopItem::updateOrCreate([
                    'shop_id' => $row['row_id'], 'subrow_id' => $row['subrow_id'],
                ], [
                    'item_id' => $f['Item']['row_id'], 'price' => $f['Item']['fields']['PriceMid'],
                    'is_hq' => false, 'source_data' => $f,
                ])->id;
            }
            foreach (array_chunk($itemIds, 500) as $ids) {
                GilShopItem::whereIn('item_id', $ids)->whereNotIn('id', $offerIds)->delete();
            }
            foreach ($npcs as $id => $row) {
                VendorNpc::updateOrCreate(['id' => $id], [
                    'name' => $row['fields']['Singular'], 'title' => $row['fields']['Title'] ?: null,
                ]);
                $locationIds = [];
                foreach ($locations[$id] ?? [] as $location) {
                    $locationIds[] = VendorLocation::updateOrCreate([
                        'vendor_npc_id' => $id, 'source' => $location['source'], 'source_key' => $location['source_key'],
                    ], $location)->id;
                }
                VendorLocation::where('vendor_npc_id', $id)->whereNotIn('id', $locationIds)->delete();
            }
            foreach ($shops as $shopId => $row) {
                $vendors = [];
                foreach ($links as $npcId => $npcShops) {
                    if (isset($npcShops[$shopId])) {
                        $vendors[$npcId] = ['source_data' => json_encode($npcShops[$shopId], JSON_THROW_ON_ERROR)];
                    }
                }
                GilShop::findOrFail($shopId)->vendors()->sync($vendors);
            }
        });

        return ['shops' => count($shops), 'offers' => count($offers), 'vendors' => count($npcs),
            'locations' => array_sum(array_map('count', $locations))];
    }
}
