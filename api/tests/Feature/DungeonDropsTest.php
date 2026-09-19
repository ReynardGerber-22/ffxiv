<?php

namespace Tests\Feature;

use App\Console\Commands\SyncDungeonDrops;
use App\Models\DungeonDrop;
use App\Models\Item;
use App\Services\DungeonDropService;
use App\Services\XivApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DungeonDropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_is_repeatable_and_material_api_returns_multiple_duty_names(): void
    {
        Item::create(['id' => 5313, 'name' => 'Dodore Wing']);
        Http::preventStrayRequests();
        Http::fake([
            SyncDungeonDrops::SOURCES_URL => Http::response([5313 => [5, 5, 6, -50, 16], 999 => [5]]),
            SyncDungeonDrops::INSTANCES_URL => Http::response([5 => ['en' => 'the Aurum Vale'], 6 => ['en' => 'Another Duty']]),
        ]);
        $this->artisan('dungeons:sync')->assertSuccessful();
        $ids = DungeonDrop::pluck('id')->all();
        $this->artisan('dungeons:sync', ['--item' => [5313]])->assertSuccessful();
        $this->assertSame($ids, DungeonDrop::pluck('id')->all());
        $this->assertDatabaseCount('dungeon_drops', 2);
        $this->mock(XivApiService::class)->shouldReceive('getExpandedMaterialList')->once()->andReturn([
            ['id' => 5313, 'name' => 'Dodore Wing', 'quantity' => 1],
            ['id' => 999, 'name' => 'Other item', 'quantity' => 1],
        ]);
        $this->getJson('/api/expanded-materials?job=Leatherworker&minLevel=46&maxLevel=50')
            ->assertOk()->assertJsonPath('0.dungeons', [
                ['id' => 6, 'name' => 'Another Duty'], ['id' => 5, 'name' => 'The Aurum Vale'],
            ])->assertJsonPath('1.dungeons', [])->assertJsonPath('0.mobDrops', []);
        Http::assertSentCount(4);
    }

    public function test_invalid_upstream_metadata_preserves_existing_links(): void
    {
        Item::create(['id' => 5313, 'name' => 'Dodore Wing']);
        DungeonDrop::create(['item_id' => 5313, 'instance_id' => 5, 'name' => 'The Aurum Vale']);
        Http::preventStrayRequests();
        Http::fake([
            SyncDungeonDrops::SOURCES_URL => Http::response([5313 => [5]]),
            SyncDungeonDrops::INSTANCES_URL => Http::response([5 => ['en' => '']]),
        ]);
        $this->artisan('dungeons:sync')->assertFailed();
        $this->assertDatabaseHas('dungeon_drops', ['item_id' => 5313, 'name' => 'The Aurum Vale']);
        $this->assertSame([['id' => 5313, 'dungeons' => [['id' => 5, 'name' => 'The Aurum Vale']]]],
            app(DungeonDropService::class)->enrichMaterials([['id' => 5313]]));
        Http::assertSentCount(2);
    }
}
