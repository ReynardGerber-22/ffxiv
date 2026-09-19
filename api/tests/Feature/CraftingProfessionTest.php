<?php

namespace Tests\Feature;

use App\Services\XivApiService;
use Tests\TestCase;

class CraftingProfessionTest extends TestCase
{
    public function test_crafting_materials_include_the_selected_recipe_profession_for_each_item(): void
    {
        $this->partialMock(XivApiService::class, function ($mock) {
            $mock->shouldReceive('getMaterialList')->once()->with('Smithing', 1, 10)->andReturn([
                ['id' => 10, 'name' => 'Ingot', 'quantity' => 3],
                ['id' => 20, 'name' => 'Lumber', 'quantity' => 2],
            ]);
            $mock->shouldReceive('findRecipesByItemIds')->once()->with([10, 20], 'Smithing')->andReturn([
                10 => ['job' => 'Smithing', 'amountResult' => 1, 'ingredients' => []],
                20 => ['job' => 'Woodworking', 'amountResult' => 3, 'ingredients' => []],
            ]);
        });

        $this->getJson('/api/crafting-materials?job=Blacksmith&minLevel=1&maxLevel=10')
            ->assertOk()
            ->assertExactJson([
                ['id' => 10, 'name' => 'Ingot', 'quantity' => 3, 'profession' => 'Blacksmith'],
                ['id' => 20, 'name' => 'Lumber', 'quantity' => 3, 'profession' => 'Carpenter'],
            ]);
    }
}
