<?php

namespace App\Services;

use App\Models\FishingBait;
use App\Models\FishingSpot;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class TeamcraftFishingService
{
    public const URL = 'https://raw.githubusercontent.com/ffxiv-teamcraft/ffxiv-teamcraft/staging/libs/data/src/lib/json/fishing-sources.json';

    public function __construct(private ItemSyncService $items, private FishingSyncService $fishing) {}

    public function fetch(): array
    {
        $data = Http::timeout(60)->retry(3, 1000)->get(self::URL)->throw()->json();
        if (! is_array($data) || $data === []) {
            throw new RuntimeException('Teamcraft returned an empty or invalid dataset.');
        }
        foreach ($data as $fishId => $records) {
            Validator::make(['fish' => $fishId, 'records' => $records], [
                'fish' => 'required|integer|min:1',
                'records' => 'required|array|min:1',
                'records.*' => 'required|array',
                'records.*.spot' => 'required|integer|min:1',
                'records.*.bait' => 'required|integer|min:1',
            ])->validate();
        }

        return $data;
    }

    public function importFish(int $fishId, array $records): int
    {
        // Resolve external metadata before replacing this fish's previous recommendations.
        foreach (array_unique([$fishId, ...array_column($records, 'bait')]) as $id) {
            if (! Item::whereKey($id)->exists()) {
                $this->items->sync($id);
            }
        }
        foreach (array_unique(array_column($records, 'spot')) as $spotId) {
            if (! FishingSpot::whereKey($spotId)->exists()) {
                $this->fishing->syncSpot($spotId);
            }
        }

        return DB::transaction(function () use ($fishId, $records) {
            $ids = [];
            $fish = Item::findOrFail($fishId);
            foreach ($records as $record) {
                $canonical = $record;
                ksort($canonical);
                $bait = FishingBait::updateOrCreate([
                    'fish_item_id' => $fishId,
                    'fishing_spot_id' => $record['spot'],
                    'bait_item_id' => $record['bait'],
                    'source_hash' => hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR)),
                ], ['source_data' => $record]);
                $ids[] = $bait->id;
            }
            $fish->fishingSpots()->syncWithoutDetaching(array_unique(array_column($records, 'spot')));
            $fish->fishingBaits()->whereNotIn('id', $ids)->delete();

            return count(array_unique($ids));
        });
    }

    public function prepareItems(array $data): void
    {
        $ids = array_keys($data);
        foreach ($data as $records) {
            array_push($ids, ...array_column($records, 'bait'));
        }
        $ids = array_values(array_unique($ids));
        $existing = Item::whereIn('id', $ids)->pluck('id')->all();
        $this->items->syncMany(array_values(array_diff($ids, $existing)));
    }
}
