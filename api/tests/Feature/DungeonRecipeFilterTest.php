<?php

namespace Tests\Feature;

use App\Models\DungeonDrop;
use App\Models\GatheringNode;
use App\Models\Item;
use App\Services\SpecialSourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DungeonRecipeFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(SpecialSourceService::class)->shouldReceive('blockedItemIds')->andReturn([])->byDefault();
        Item::create(['id' => 900, 'name' => 'Faded Orchestrion Roll']);
        DungeonDrop::create(['item_id' => 900, 'instance_id' => 5, 'name' => 'Test Dungeon']);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            $query = $request['query'];
            $rows = str_contains($query, 'CraftType.Name') ? [
                $this->recipe(1000, 'Safe Recipe', [10 => 2, 1 => 3]),
                $this->recipe(1001, 'Orchestrion Roll', [900 => 1, 10 => 4, 1 => 5]),
                $this->recipe(1002, 'Nested Drop Recipe', [20 => 1, 1 => 7]),
            ] : [];
            if (! str_contains($query, 'CraftType.Name')) {
                preg_match_all('/ItemResult=(\d+)/', $query, $matches);
                foreach ($matches[1] as $id) {
                    $recipe = match ((int) $id) {
                        10 => $this->recipe(10, 'Ink', [100 => 2, 1 => 1], 2),
                        20 => $this->recipe(20, 'Intermediate', [21 => 1]),
                        21 => $this->recipe(21, 'Nested Intermediate', [900 => 1]),
                        default => null,
                    };
                    if ($recipe !== null) {
                        $rows[] = $recipe;
                    }
                }
            }

            return Http::response(['results' => $rows]);
        });
    }

    private function recipe(int $id, string $name, array $ingredients, int $yield = 1): array
    {
        return ['row_id' => $id, 'fields' => [
            'ItemResult' => ['row_id' => $id, 'fields' => ['Name' => $name]],
            'CraftType' => ['fields' => ['Name' => 'Alchemy']],
            'RecipeLevelTable' => ['fields' => ['ClassJobLevel' => 48, 'Stars' => 0]],
            'AmountResult' => $yield,
            'Ingredient' => array_map(fn ($itemId) => ['row_id' => $itemId, 'fields' => ['Name' => 'Item '.$itemId]], array_keys($ingredients)),
            'AmountIngredient' => array_values($ingredients),
        ]];
    }

    public function test_filter_removes_direct_and_nested_duty_recipes_before_aggregation(): void
    {
        $query = '?job=Alchemist&minLevel=46&maxLevel=50&includeDungeonDrops=0';
        $this->getJson('/api/recipes'.$query)->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Safe Recipe');
        $this->getJson('/api/materials'.$query)->assertOk()->assertExactJson([
            ['id' => 10, 'name' => 'Item 10', 'quantity' => 2],
            ['id' => 1, 'name' => 'Item 1', 'quantity' => 3],
        ]);
        $this->getJson('/api/crafting-materials'.$query)->assertOk()->assertExactJson([
            ['id' => 10, 'name' => 'Item 10', 'quantity' => 2, 'profession' => 'Alchemist'],
        ]);
        $materials = collect($this->getJson('/api/expanded-materials'.$query)->assertOk()->json())->keyBy('id');
        $this->assertCount(2, $materials);
        $this->assertSame(2, $materials[100]['quantity']);
        $this->assertSame(4, $materials[1]['quantity']);
    }

    public function test_default_and_checked_setting_keep_all_recipes_and_quantities(): void
    {
        $query = '?job=Alchemist&minLevel=46&maxLevel=50';
        foreach (['', '&includeDungeonDrops=1'] as $setting) {
            $this->getJson('/api/recipes'.$query.$setting)->assertOk()->assertJsonCount(3);
            $materials = collect($this->getJson('/api/expanded-materials'.$query.$setting)->assertOk()->json())->keyBy('id');
            $this->assertSame(2, $materials[900]['quantity']);
            $this->assertSame(6, $materials[100]['quantity']);
            $this->assertSame(18, $materials[1]['quantity']);
        }
    }

    public function test_gatherable_dungeon_loot_does_not_exclude_recipes(): void
    {
        Item::create(['id' => 1, 'name' => 'Water Shard']);
        DungeonDrop::create(['item_id' => 1, 'instance_id' => 5, 'name' => 'Test Dungeon']);
        $node = GatheringNode::create([
            'id' => 1, 'gathering_type' => 'Mining', 'gathering_level' => 1,
            'area_name' => 'Test Area', 'territory_name' => 'Test Territory', 'map_id' => 1,
            'map_size_factor' => 100, 'map_offset_x' => 0, 'map_offset_y' => 0,
        ]);
        $node->items()->attach(1, ['gathering_item_id' => 1]);
        $this->getJson('/api/recipes?job=Alchemist&minLevel=46&maxLevel=50&includeDungeonDrops=0')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Safe Recipe');
    }

    public function test_craftable_loot_uses_its_recipe_instead_of_requiring_a_drop(): void
    {
        Item::create(['id' => 10, 'name' => 'Ink']);
        DungeonDrop::create(['item_id' => 10, 'instance_id' => 5, 'name' => 'Test Dungeon']);
        $this->getJson('/api/crafting-materials?job=Alchemist&minLevel=46&maxLevel=50&includeDungeonDrops=0')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', 10)->assertJsonPath('0.quantity', 2);
    }

    public function test_special_source_dependency_recalculates_all_totals(): void
    {
        DungeonDrop::query()->delete();
        $this->mock(SpecialSourceService::class)->shouldReceive('blockedItemIds')->andReturn([900]);
        $query = '?job=Alchemist&minLevel=46&maxLevel=50&includeSpecialSources=0';
        $this->getJson('/api/recipes'.$query)->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Safe Recipe');
        $this->getJson('/api/crafting-materials'.$query)->assertOk()->assertJsonCount(1)->assertJsonPath('0.quantity', 2);
        $materials = collect($this->getJson('/api/expanded-materials'.$query)->assertOk()->json())->keyBy('id');
        $this->assertCount(2, $materials);
        $this->assertSame(2, $materials[100]['quantity']);
        $this->assertSame(4, $materials[1]['quantity']);
        $this->getJson('/api/recipes?job=Alchemist&minLevel=46&maxLevel=50&includeSpecialSources=1')
            ->assertOk()->assertJsonCount(3);
        $this->getJson('/api/recipes?job=Alchemist&minLevel=46&maxLevel=50&includeSpecialSources=invalid')
            ->assertUnprocessable()->assertJsonValidationErrors('includeSpecialSources');
    }

    public function test_invalid_filter_is_rejected(): void
    {
        $this->getJson('/api/materials?job=Alchemist&minLevel=46&maxLevel=50&includeDungeonDrops=invalid')
            ->assertUnprocessable()->assertJsonValidationErrors('includeDungeonDrops');
        Http::assertNothingSent();
    }

    public function test_all_recipes_can_be_excluded(): void
    {
        Item::create(['id' => 100, 'name' => 'Another Drop']);
        DungeonDrop::create(['item_id' => 100, 'instance_id' => 5, 'name' => 'Test Dungeon']);
        foreach (['recipes', 'materials', 'crafting-materials', 'expanded-materials'] as $endpoint) {
            $this->getJson('/api/'.$endpoint.'?job=Alchemist&minLevel=46&maxLevel=50&includeDungeonDrops=0')
                ->assertOk()->assertExactJson([]);
        }
    }
}
