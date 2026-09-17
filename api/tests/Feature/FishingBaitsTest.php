<?php

namespace Tests\Feature;

use App\Models\FishingBait;
use App\Models\FishingSpot;
use App\Models\Item;
use App\Services\FishingService;
use App\Services\ItemSyncService;
use App\Services\TeamcraftFishingService;
use App\Services\XivApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FishingBaitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_preserves_alternatives_conditions_and_mooch_items_and_is_repeatable(): void
    {
        Http::preventStrayRequests();
        foreach ([39, 40, 237] as $id) {
            FishingSpot::create(['id' => $id, 'name' => "Spot {$id}", 'fishing_level' => 5, 'territory_name' => 'Test']);
        }
        $data = [
            4930 => [['spot' => 39, 'bait' => 2588], ['spot' => 40, 'bait' => 2588]],
            4776 => [['spot' => 39, 'bait' => 2585], ['spot' => 40, 'bait' => 2587]],
            28937 => [['spot' => 237, 'bait' => 29716], ['spot' => 237, 'bait' => 29717], ['spot' => 237, 'bait' => 29717, 'spawn' => 9]],
            4924 => [['spot' => 39, 'bait' => 4904]],
        ];
        Http::fake([
            TeamcraftFishingService::URL => Http::response($data),
            'v2.xivapi.com/api/sheet/Item?*' => function ($request) {
                return Http::response(['rows' => array_map(fn ($id) => [
                    'row_id' => (int) $id, 'fields' => ['Name' => 'Test bait'],
                ], explode(',', $request['rows']))]);
            },
        ]);
        $this->artisan('fishing:import-baits')->assertSuccessful();
        $this->artisan('fishing:import-baits')->assertSuccessful();
        $this->assertDatabaseCount('fishing_baits', 8);
        $this->assertDatabaseHas('items', ['id' => 4904]);
        $this->assertSame(9, FishingBait::where('fish_item_id', 28937)->whereNotNull('source_data->spawn')->first()->source_data['spawn']);

        DB::enableQueryLog();
        $result = app(FishingService::class)->enrichMaterials(array_map(fn ($id) => ['id' => $id], array_keys($data)));
        $this->assertCount(4, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame([2588], array_column($result[0]['fishing'][0]['baits'], 'id'));
        $this->assertSame([2585], array_column($result[1]['fishing'][0]['baits'], 'id'));
        $this->assertSame([2587], array_column($result[1]['fishing'][1]['baits'], 'id'));
        $this->assertSame([29716, 29717], array_column($result[2]['fishing'][0]['baits'], 'id'));

        app(TeamcraftFishingService::class)->importFish(28937, [['spot' => 237, 'bait' => 29716]]);
        $this->assertSame(1, FishingBait::where('fish_item_id', 28937)->count());
        $this->assertSame(2, FishingBait::where('fish_item_id', 4930)->count());
    }

    public function test_expanded_materials_endpoint_adds_baits_without_network_requests(): void
    {
        Http::preventStrayRequests();
        Item::create(['id' => 4930, 'name' => 'Princess Trout']);
        Item::create(['id' => 2588, 'name' => 'Crayfish Ball']);
        FishingSpot::create(['id' => 41, 'name' => 'Nym River', 'fishing_level' => 5, 'territory_name' => 'Middle La Noscea']);
        app(TeamcraftFishingService::class)->importFish(4930, [['spot' => 41, 'bait' => 2588]]);
        $this->mock(XivApiService::class)->shouldReceive('getExpandedMaterialList')->once()->andReturn([
            ['id' => 4930, 'name' => 'Princess Trout', 'quantity' => 1],
        ]);
        $this->getJson('/api/expanded-materials?job=Culinarian&minLevel=1&maxLevel=5')
            ->assertOk()->assertJsonPath('0.fishing.0.baits.0.name', 'Crayfish Ball');
    }

    public function test_invalid_dataset_is_rejected_before_writes(): void
    {
        Http::fake([TeamcraftFishingService::URL => Http::response([4930 => [['spot' => 39]]])]);
        try {
            app(TeamcraftFishingService::class)->fetch();
            $this->fail('Invalid data should fail validation.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('fishing_baits', 0);
        }
    }

    public function test_metadata_failure_keeps_previous_recommendations(): void
    {
        Item::create(['id' => 4930, 'name' => 'Princess Trout']);
        Item::create(['id' => 2588, 'name' => 'Crayfish Ball']);
        FishingSpot::create(['id' => 39, 'name' => 'Rogue River', 'fishing_level' => 1, 'territory_name' => 'Middle La Noscea']);
        $service = app(TeamcraftFishingService::class);
        $service->importFish(4930, [['spot' => 39, 'bait' => 2588]]);
        Http::fake(['v2.xivapi.com/*' => Http::response([], 503)]);
        try {
            $service->importFish(4930, [['spot' => 39, 'bait' => 2585]]);
            $this->fail('Metadata failure should propagate.');
        } catch (RequestException) {
            $this->assertDatabaseCount('fishing_baits', 1);
            $this->assertDatabaseHas('fishing_baits', ['fish_item_id' => 4930, 'bait_item_id' => 2588]);
        }
    }

    public function test_item_metadata_is_batched_and_preserves_requested_order(): void
    {
        Http::preventStrayRequests();
        Http::fake(['v2.xivapi.com/api/sheet/Item?*' => function ($request) {
            return Http::response(['rows' => array_map(fn ($id) => [
                'row_id' => (int) $id, 'fields' => ['Name' => "Item {$id}"],
            ], array_reverse(explode(',', $request['rows'])))]);
        }]);
        $ids = [...range(1, 101), 1];
        $items = app(ItemSyncService::class)->syncMany($ids);
        $this->assertSame($ids, array_map(fn ($item) => $item->id, $items));
        Http::assertSentCount(2);
        $this->assertDatabaseCount('items', 101);
    }
}
