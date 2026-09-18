<?php

namespace Tests\Feature;

use App\Models\GatheringNode;
use App\Models\GilShopItem;
use App\Models\Item;
use App\Models\VendorLocation;
use App\Models\VendorNpc;
use App\Services\VendorLocationService;
use App\Services\VendorService;
use App\Services\VendorSyncService;
use App\Services\XivApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VendorsTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSources(): void
    {
        Http::preventStrayRequests();
        foreach ([5106 => 'Copper Ore', 5111 => 'Iron Ore', 5291 => 'Animal Skin', 5530 => 'Coke'] as $id => $name) {
            Item::firstOrCreate(['id' => $id], ['name' => $name]);
        }
        $row = fn ($id, $fields) => ['row_id' => $id, 'fields' => $fields];
        $offer = fn ($shop, $sub, $item, $price, $hq = false) => [
            'row_id' => $shop, 'subrow_id' => $sub, 'fields' => [
                'Item' => ['row_id' => $item, 'fields' => ['Name' => 'Material', 'PriceMid' => $price]],
                'IsHQ' => $hq, 'QuestRequired@as(raw)' => [123, 0], 'StateRequired' => 0,
            ],
        ];
        $offers = [
            $offer(262172, 0, 5106, 2), $offer(262173, 0, 5106, 2),
            $offer(262173, 1, 5111, 18), $offer(262172, 2, 5291, 4),
            $offer(262172, 3, 5111, 18, true),
        ];
        $map = fn ($id, $name, $territory) => $row($id, [
            'SizeFactor' => 200, 'OffsetX' => 0, 'OffsetY' => 0,
            'PlaceNameSub' => ['fields' => ['Name' => '']],
            'TerritoryType' => $row($territory, ['PlaceName' => ['fields' => ['Name' => $name]]]),
        ]);
        Http::fake(function ($request) use ($row, $offers, $map) {
            if ($request->url() === VendorLocationService::TEAMCRAFT_URL) {
                return Http::response([
                    1000999 => ['position' => ['map' => 11, 'x' => 10.66, 'y' => 15.21]],
                    // Must not override an existing XIVAPI placement.
                    1000236 => ['position' => ['map' => 11, 'x' => 99, 'y' => 99]],
                ]);
            }
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/api/search') {
                $query = $request['query'];
                $results = [];
                switch ($request['sheets']) {
                    case 'GilShopItem':
                        return Http::response(isset($request['cursor'])
                            ? ['results' => array_slice($offers, 2)]
                            : ['results' => array_slice($offers, 0, 2), 'next' => 'second-page']);
                    case 'ENpcBase':
                        foreach ([1000236 => [262172, 262173, 3538946], 1000999 => [3538946], 1000240 => [262172], 1099999 => [262172]] as $id => $links) {
                            if (collect($links)->contains(fn ($link) => str_contains($query, '='.$link))) {
                                $results[] = $row($id, ['ENpcData@as(raw)' => $links]);
                            }
                        }
                        break;
                    case 'TopicSelect':
                        if (str_contains($query, '=262173') || str_contains($query, '=3538946')) {
                            $results[] = $row(3276801, ['Shop@as(raw)' => [262173, 3538946], 'Name' => 'Menu']);
                        }
                        break;
                    case 'PreHandler':
                        if (str_contains($query, '=3276801')) {
                            $results[] = $row(3538946, ['Target@as(raw)' => 3276801, 'UnlockQuest@as(raw)' => 123]);
                        }
                        break;
                    case 'Level':
                        foreach ([1141252 => 158.156, 1141253 => 200.0] as $id => $x) {
                            $results[] = $row($id, [
                                'Object@as(raw)' => 1000236, 'Type' => 8, 'X' => $x, 'Y' => 15.671, 'Z' => -114.511,
                                'Map' => $map(3, 'Old Gridania', 133),
                                'Territory' => $row(133, ['PlaceName' => ['fields' => ['Name' => 'Old Gridania']]]),
                            ]);
                        }
                        break;
                    default:
                        throw new \RuntimeException('Unexpected sheet: '.$request['sheets']);
                }

                return Http::response(['results' => $results]);
            }
            $rows = [];
            foreach (explode(',', $request['rows']) as $id) {
                $id = (int) $id;
                $rows[] = match ($path) {
                    '/api/sheet/GilShop' => $row($id, ['Name' => '', 'Quest@as(raw)' => 0, 'FestivalId' => 0, 'FestivalPhase' => 0]),
                    '/api/sheet/ENpcResident' => $row($id, [
                        'Singular' => $id === 1099999 ? ' material supplier ' : ($id === 1000999 ? 'Soemrwyb' : "O'rhoyod"),
                        'Title' => $id === 1000999 ? 'Guild Supplier' : 'Tradecraft Supplier',
                    ]),
                    '/api/sheet/Map' => $map(11, 'Limsa Lominsa Upper Decks', 128),
                    default => throw new \RuntimeException('Unexpected request: '.$request->url()),
                };
            }

            return Http::response(['rows' => $rows]);
        });
    }

    public function test_import_is_gil_only_paginated_repeatable_and_resolves_cycles_and_locations(): void
    {
        $this->fakeSources();
        VendorNpc::create(['id' => 1099999, 'name' => ' Ananta material supplier ']);
        VendorNpc::create(['id' => 1099998, 'name' => ' Allagan resupply node ']);
        $this->artisan('vendors:sync', ['--item' => [5106, 5111, 5291, 5530]])->assertSuccessful();
        $before = GilShopItem::pluck('id')->all();
        $this->artisan('vendors:sync', ['--item' => [5106, 5111, 5291, 5530]])->assertSuccessful();
        $this->assertSame($before, GilShopItem::pluck('id')->all());
        $this->assertDatabaseCount('gil_shop_items', 4);
        $this->assertDatabaseMissing('vendor_npcs', ['id' => 1099999]);
        $this->assertDatabaseMissing('vendor_npcs', ['id' => 1099998]);
        $this->assertDatabaseMissing('vendor_shop_links', ['vendor_npc_id' => 1099999]);
        $this->assertDatabaseMissing('gil_shop_items', ['item_id' => 5530]);
        $this->assertDatabaseMissing('gil_shop_items', ['is_hq' => true]);
        $this->assertDatabaseCount('vendor_shop_links', 4);
        $link = json_decode(DB::table('vendor_shop_links')->where('vendor_npc_id', 1000999)->value('source_data'), true);
        $this->assertSame([3538946, 3276801, 262173], $link['paths'][0]);
        $this->assertSame(123, $link['handlers'][3538946]['fields']['UnlockQuest@as(raw)']);
        $this->assertDatabaseCount('vendor_locations', 3);
        $this->assertDatabaseHas('vendor_locations', ['vendor_npc_id' => 1000999, 'source' => 'teamcraft', 'x' => 10.66]);
        $this->assertSame([123, 0], GilShopItem::first()->source_data['QuestRequired@as(raw)']);
        $location = VendorLocation::where('source_key', '1141252')->firstOrFail();
        $this->assertSame(14.4, round($location->x, 1));
        $this->assertSame(9.0, round($location->y, 1));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'SpecialShop'));

        DB::enableQueryLog();
        $materials = app(VendorService::class)->enrichMaterials([['id' => 5106], ['id' => 5111], ['id' => 5530]]);
        $this->assertCount(4, DB::getQueryLog());
        DB::disableQueryLog();
        // Two real placements for O'rhoyod, Soemrwyb, and an unresolved same-name NPC.
        $this->assertCount(4, $materials[0]['vendors']);
        $this->assertFalse($materials[0]['vendors'][3]['locationResolved']);
        $this->assertNull($materials[0]['vendors'][3]['x']);
        $this->assertSame([], $materials[2]['vendors']);
    }

    public function test_material_api_retains_gathering_and_adds_vendor_titles_and_unit_prices(): void
    {
        $this->fakeSources();
        app(VendorSyncService::class)->sync([5106, 5111, 5291, 5530]);
        $placeholder = VendorNpc::create(['id' => 9999999, 'name' => ' Ananta material supplier ']);
        $placeholder->shops()->attach(262172);
        $resupplyNode = VendorNpc::create(['id' => 9999998, 'name' => 'Allagan resupply node']);
        $resupplyNode->shops()->attach(262172);
        $node = GatheringNode::create([
            'id' => 10, 'gathering_type' => 'Mining', 'gathering_level' => 1,
            'area_name' => 'Test', 'territory_name' => 'Test', 'map_id' => 3,
            'map_size_factor' => 100, 'map_offset_x' => 0, 'map_offset_y' => 0, 'raw_x' => 0, 'raw_y' => 0,
        ]);
        $node->items()->attach([5106 => ['gathering_item_id' => 75], 5111 => ['gathering_item_id' => 74]]);
        $this->mock(XivApiService::class)->shouldReceive('getExpandedMaterialList')->once()->andReturn([
            ['id' => 5106, 'name' => 'Copper Ore', 'quantity' => 20],
            ['id' => 5111, 'name' => 'Iron Ore', 'quantity' => 20],
            ['id' => 5530, 'name' => 'Coke', 'quantity' => 20],
        ]);
        $response = $this->getJson('/api/expanded-materials?job=Blacksmith&minLevel=1&maxLevel=20')
            ->assertOk()->assertJsonCount(1, '0.gathering')->assertJsonCount(1, '1.gathering')
            ->assertJsonPath('0.vendors.0.price', 2)->assertJsonPath('1.vendors.0.price', 18)
            ->assertJsonPath('2.vendors', []);
        $soemrwyb = collect($response->json('1.vendors'))->firstWhere('id', 1000999);
        $this->assertSame('Guild Supplier', $soemrwyb['title']);
        $this->assertSame(10.7, $soemrwyb['x']);
        $this->assertNull(collect($response->json('0.vendors'))->firstWhere('id', 9999999));
        $this->assertNull(collect($response->json('0.vendors'))->firstWhere('id', 9999998));
    }

    public function test_upstream_failure_does_not_delete_cached_offers(): void
    {
        $this->fakeSources();
        $service = app(VendorSyncService::class);
        $service->sync([5106, 5111, 5291, 5530]);
        $this->mock(XivApiService::class)->shouldReceive('searchAll')->andThrow(new \RuntimeException('Offline'));
        try {
            app(VendorSyncService::class)->sync([5106]);
            $this->fail('Expected upstream error');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Offline', $exception->getMessage());
            $this->assertDatabaseCount('gil_shop_items', 4);
        }
    }

    public function test_world_coordinate_conversion_uses_scale_and_offsets(): void
    {
        $this->assertEqualsWithDelta(7.32735, VendorLocationService::mapCoordinate(-195.941, 0, 200), 0.001);
        $this->assertEqualsWithDelta(10.708, VendorLocationService::mapCoordinate(-27.0713, 0, 200), 0.001);
        $this->assertEqualsWithDelta(22.5009765625, VendorLocationService::mapCoordinate(0, 50, 100), 0.001);
    }

    public function test_refresh_removes_stale_offers_without_deleting_other_items(): void
    {
        $this->fakeSources();
        app(VendorSyncService::class)->sync([5106, 5111, 5291, 5530]);
        $this->mock(XivApiService::class)->shouldReceive('searchAll')->once()->andReturn([]);
        app(XivApiService::class)->shouldReceive('getSheetRows')->andReturn([]);
        app(VendorSyncService::class)->sync([5111]);
        $this->assertDatabaseMissing('gil_shop_items', ['item_id' => 5111]);
        $this->assertDatabaseHas('gil_shop_items', ['item_id' => 5106]);
    }
}
