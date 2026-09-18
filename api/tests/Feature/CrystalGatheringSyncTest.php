<?php

namespace Tests\Feature;

use App\Models\GatheringNode;
use App\Models\GatheringNodeItem;
use App\Models\Item;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrystalGatheringSyncTest extends TestCase
{
    use RefreshDatabase;

    private function point(int $base, int $item = 2): array
    {
        return ['row_id' => 30000 + $base, 'fields' => [
            'GatheringPointBase' => ['row_id' => $base, 'fields' => [
                'GatheringLevel' => 5, 'GatheringType' => ['fields' => ['Name' => 'Mining']],
                'Item' => [['sheet' => 'GatheringItem', 'row_id' => $item + 100, 'fields' => ['Item@as(raw)' => $item]]],
            ]],
            'PlaceName' => ['fields' => ['Name' => 'Test area']],
            'TerritoryType' => ['fields' => ['PlaceName' => ['fields' => ['Name' => 'Test territory']], 'Map@as(raw)' => 15]],
        ]];
    }

    private function sheetResponse($request): PromiseInterface
    {
        $path = parse_url($request->url(), PHP_URL_PATH);
        $rows = [];
        foreach (explode(',', $request['rows']) as $id) {
            $fields = match ($path) {
                '/api/sheet/ExportedGatheringPoint' => ['X' => 0, 'Y' => -300, 'Radius' => 40],
                '/api/sheet/Map' => ['SizeFactor' => 100, 'OffsetX' => 0, 'OffsetY' => 0],
                '/api/sheet/Item' => ['Name' => 'Fire Shard'],
                default => throw new \RuntimeException('Unexpected request: '.$request->url()),
            };
            $rows[] = ['row_id' => (int) $id, 'fields' => $fields];
        }

        return Http::response(['rows' => $rows]);
    }

    public function test_pages_are_saved_incrementally_and_reruns_preserve_unique_nodes_and_links(): void
    {
        Item::create(['id' => 2, 'name' => 'Fire Shard']);
        Item::create(['id' => 5106, 'name' => 'Copper Ore']);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (($request['sheets'] ?? null) === 'GatheringPoint') {
                $this->assertSame('+GatheringPointBase.Item[].Item=2 +TerritoryType.Map>0 +PlaceName>0', $request['query']);
                $this->assertSame(100, $request['limit']);
                if (($request['cursor'] ?? null) === 'page-2') {
                    $this->assertDatabaseHas('gathering_node_items', ['item_id' => 2, 'gathering_node_id' => 30]);

                    return Http::response(['results' => [$this->point(30), $this->point(31)]]);
                }

                return Http::response(['results' => [$this->point(30), $this->point(30)], 'next' => 'page-2']);
            }

            return $this->sheetResponse($request);
        });
        $this->artisan('crystals:sync', ['--item' => 'Fire Shard'])->assertSuccessful();
        GatheringNode::findOrFail(30)->items()->attach(5106, ['gathering_item_id' => 75]);
        $ids = GatheringNodeItem::orderBy('id')->pluck('id')->all();
        $this->artisan('crystals:sync', ['--item' => 'Fire Shard'])->assertSuccessful();
        $this->assertDatabaseCount('gathering_nodes', 2);
        $this->assertDatabaseCount('gathering_node_items', 3);
        $this->assertSame($ids, GatheringNodeItem::orderBy('id')->pluck('id')->all());
        $this->assertNotNull(Item::findOrFail(2)->gathering_checked_at);
        $this->assertDatabaseHas('gathering_nodes', ['id' => 30, 'raw_x' => 0, 'raw_y' => -300, 'area_name' => 'Test area']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sheet/GatheringItem/'));
        Http::assertSentCount(12);
    }

    public function test_missing_local_item_is_resolved_by_exact_name_and_synced_through_item_metadata_service(): void
    {
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (($request['sheets'] ?? null) === 'Item') {
                $this->assertSame('Name="Fire Shard"', $request['query']);

                return Http::response(['results' => [
                    ['row_id' => 9000, 'fields' => ['Name' => 'Fire Shard']],
                    ['row_id' => 9001, 'fields' => ['Name' => 'Other Fire Shard']],
                ]]);
            }
            if (($request['sheets'] ?? null) === 'GatheringPoint') {
                $this->assertStringContainsString('Item=9000 ', $request['query']);

                return Http::response(['results' => [$this->point(30, 9000)]]);
            }

            return $this->sheetResponse($request);
        });
        $this->artisan('crystals:sync', ['--item' => 'Fire Shard'])->assertSuccessful();
        $this->assertDatabaseHas('items', ['id' => 9000, 'name' => 'Fire Shard']);
        $this->assertDatabaseHas('gathering_node_items', ['item_id' => 9000, 'gathering_node_id' => 30, 'gathering_item_id' => 9100]);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sheet/Item?'));
    }

    public function test_failed_page_preserves_previous_writes_and_continues_with_next_crystal(): void
    {
        foreach (['Fire', 'Ice', 'Wind', 'Earth', 'Lightning', 'Water'] as $index => $element) {
            Item::create(['id' => $index + 2, 'name' => $element.' Shard']);
        }
        Http::preventStrayRequests();
        $failPage = true;
        Http::fake(function ($request) use (&$failPage) {
            if (($request['sheets'] ?? null) === 'GatheringPoint') {
                if (($request['cursor'] ?? null) === 'bad-page') {
                    return Http::response($failPage ? ['error' => 'Invalid page'] : ['results' => [$this->point(31)]]);
                }
                if (str_contains($request['query'], 'Item=2 ')) {
                    return Http::response(['results' => [$this->point(30)], 'next' => 'bad-page']);
                }

                return Http::response(['results' => []]);
            }

            return $this->sheetResponse($request);
        });
        $this->artisan('crystals:sync', ['--type' => 'shard'])->assertFailed();
        $this->assertDatabaseHas('gathering_node_items', ['item_id' => 2, 'gathering_node_id' => 30]);
        $this->assertNull(Item::findOrFail(2)->gathering_checked_at);
        $this->assertNotNull(Item::findOrFail(3)->gathering_checked_at);
        $this->assertSame(5, Item::whereNotNull('gathering_checked_at')->count());
        Http::assertSent(fn ($request) => ($request['cursor'] ?? null) === 'bad-page');
        $failPage = false;
        $this->artisan('crystals:sync', ['--item' => 'Fire Shard'])->assertSuccessful();
        $this->assertDatabaseCount('gathering_nodes', 2);
        $this->assertDatabaseCount('gathering_node_items', 2);
        $this->assertNotNull(Item::findOrFail(2)->gathering_checked_at);
    }

    public function test_missing_position_does_not_block_other_nodes_or_erase_existing_data(): void
    {
        Item::create(['id' => 2, 'name' => 'Fire Shard']);
        $existing = GatheringNode::create([
            'id' => 31, 'gathering_type' => 'Mining', 'gathering_level' => 5,
            'area_name' => 'Existing', 'territory_name' => 'Existing', 'map_id' => 15,
            'raw_x' => 123, 'raw_y' => 456, 'map_size_factor' => 100, 'map_offset_x' => 0, 'map_offset_y' => 0,
        ]);
        $existing->items()->attach(2, ['gathering_item_id' => 102]);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (($request['sheets'] ?? null) === 'GatheringPoint') {
                return Http::response(['results' => [$this->point(30), $this->point(31)]]);
            }
            if (str_contains($request->url(), '/sheet/ExportedGatheringPoint') && in_array('31', explode(',', $request['rows']), true)) {
                return Http::response([], 404);
            }

            return $this->sheetResponse($request);
        });
        $this->artisan('crystals:sync', ['--item' => 'Fire Shard'])->assertSuccessful();
        $this->assertDatabaseCount('gathering_node_items', 2);
        $this->assertDatabaseHas('gathering_nodes', ['id' => 31, 'area_name' => 'Existing', 'raw_x' => 123]);
        $this->assertDatabaseHas('gathering_nodes', ['id' => 30]);
        $this->assertNull(Item::findOrFail(2)->gathering_checked_at);
        Http::assertSent(fn ($request) => ($request['rows'] ?? null) === '30');
    }

    public function test_invalid_filters_do_not_make_requests_or_write_data(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $this->artisan('crystals:sync', ['--type' => 'ore'])->assertFailed();
        $this->artisan('crystals:sync', ['--item' => 'Copper Ore'])->assertFailed();
        $this->artisan('crystals:sync', ['--item' => 'Fire Shard', '--type' => 'cluster'])->assertFailed();
        $this->assertDatabaseCount('items', 0);
        Http::assertNothingSent();
    }

    public function test_full_sync_targets_only_the_eighteen_elemental_items(): void
    {
        $id = 50000;
        foreach (['Shard', 'Crystal', 'Cluster'] as $type) {
            foreach (['Fire', 'Ice', 'Wind', 'Earth', 'Lightning', 'Water'] as $element) {
                Item::create(['id' => $id++, 'name' => "{$element} {$type}"]);
            }
        }
        $other = Item::create(['id' => 60000, 'name' => 'Crystal Sand']);
        Http::preventStrayRequests();
        Http::fake(['https://v2.xivapi.com/api/search*' => Http::response(['results' => []])]);
        $this->artisan('crystals:sync')->assertSuccessful();
        $this->assertSame(18, Item::whereNotNull('gathering_checked_at')->count());
        $this->assertNull($other->fresh()->gathering_checked_at);
        Http::assertSentCount(18);
    }
}
