<?php

namespace App\Services;

use App\Models\Item;
use App\Models\MobSpawn;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SpecialSourceService
{
    public const BASE_URL = 'https://www.garlandtools.org/db/doc/item/en/3/';

    /** Classify raw ingredients only; the planner follows recipes for craftable items. */
    public function blockedItemIds(array $itemIds): array
    {
        $ordinary = Item::query()->whereIn('id', $itemIds)
            ->where(function ($query) {
                $query->whereHas('gatheringNodes')->orWhereHas('fishingSpots')
                    ->orWhereHas('gilShopItems', fn ($offers) => $offers->where('is_hq', false)->where('price', '>', 0))
                    ->orWhereHas('mobDrops', fn ($drops) => $drops->whereIn('bnpc_name_id', MobSpawn::query()->select('bnpc_name_id')));
            })->pluck('id')->all();
        $blocked = [];
        $missing = [];
        foreach (array_diff(array_unique($itemIds), $ordinary) as $id) {
            $cached = Cache::get('special-source:v1:'.$id);
            if ($cached === null) {
                $missing[] = $id;
            } elseif ($cached === true) {
                $blocked[] = $id;
            }
        }

        foreach (array_chunk($missing, 8) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($id) => $pool->as((string) $id)->connectTimeout(10)->timeout(25)->get(self::BASE_URL.$id.'.json'),
                $chunk,
            ));
            foreach ($chunk as $id) {
                $response = $responses[$id];
                if (! $response instanceof Response) {
                    throw new RuntimeException('Could not verify material sources. Please try calculating again.');
                }
                $item = $response->throw()->json('item');
                if (! is_array($item) || (int) ($item['id'] ?? 0) !== (int) $id || ! is_string($item['name'] ?? null)) {
                    throw new RuntimeException('Invalid material source data for item '.$id);
                }
                $special = $this->requiresSpecialSource($item);
                Cache::put('special-source:v1:'.$id, $special, now()->addDays(7));
                if ($special) {
                    $blocked[] = $id;
                }
            }
        }

        return $blocked;
    }

    private function requiresSpecialSource(array $item): bool
    {
        // Garland's vendors list represents ordinary gil purchases.
        foreach (['vendors', 'nodes', 'fishingSpots'] as $key) {
            if (! empty($item[$key])) {
                return false;
            }
        }
        $special = ! empty($item['instances']) || ! empty($item['treasure']) || ! empty($item['voyages']);
        foreach ($item['tradeShops'] ?? [] as $shop) {
            foreach ($shop['listings'] ?? [] as $listing) {
                $outputs = array_column($listing['item'] ?? [], 'id');
                if (! in_array($item['id'], $outputs)) {
                    continue;
                }
                $currencies = $listing['currency'] ?? [];
                if ($currencies === []) {
                    continue;
                }
                $onlyGil = count(array_filter($currencies, fn ($currency) => (string) $currency['id'] !== '1')) === 0;
                if ($onlyGil) {
                    return false;
                }
                $special = true;
            }
        }

        return $special;
    }
}
