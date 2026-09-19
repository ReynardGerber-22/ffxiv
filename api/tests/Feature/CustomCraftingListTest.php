<?php

namespace Tests\Feature;

use App\Services\XivApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Services\MaterialSourceService;

class CustomCraftingListTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Http::fake(function ($request) {
            $query = $request['query'];

            preg_match_all('/ItemResult=(\d+)/', $query, $matches);

            $rows = [];

            foreach ($matches[1] as $id) {
                $recipe = match ((int) $id) {
                    100 => $this->recipe(
                        100,
                        'Final Item',
                        [200 => 2],
                        3
                    ),
                    200 => $this->recipe(
                        200,
                        'Intermediate Item',
                        [300 => 3],
                        2
                    ),
                    default => null,
                };

                if ($recipe !== null) {
                    $rows[] = $recipe;
                }
            }

            return Http::response([
                'results' => $rows,
            ]);
        });
    }

    private function recipe(
        int $id,
        string $name,
        array $ingredients,
        int $yield = 1
    ): array {
        return [
            'row_id' => $id,
            'fields' => [
                'ItemResult' => [
                    'row_id' => $id,
                    'fields' => [
                        'Name' => $name,
                    ],
                ],
                'CraftType' => [
                    'fields' => [
                        'Name' => 'Smithing',
                    ],
                ],
                'RecipeLevelTable' => [
                    'fields' => [
                        'ClassJobLevel' => 50,
                        'Stars' => 0,
                    ],
                ],
                'AmountResult' => $yield,
                'Ingredient' => array_map(
                    fn($itemId) => [
                        'row_id' => $itemId,
                        'fields' => [
                            'Name' => 'Item ' . $itemId,
                        ],
                    ],
                    array_keys($ingredients)
                ),
                'AmountIngredient' => array_values($ingredients),
            ],
        ];
    }

    public function test_custom_list_expands_requested_quantities_recursively(): void
    {
        $service = app(XivApiService::class);

        $materials = $service->getCustomMaterialList([
            [
                'id' => 100,
                'quantity' => 7,
            ],
        ]);

        $this->assertCount(1, $materials);

        $this->assertSame(300, $materials[0]['id']);
        $this->assertSame('Item 300', $materials[0]['name']);
        $this->assertSame(9, $materials[0]['quantity']);
    }

    public function test_custom_materials_endpoint_calculates_requested_items(): void
    {
        $materialSourceService = $this->mock(MaterialSourceService::class);
        $materialSourceService
            ->shouldReceive('enrichMaterials')
            ->once()
            ->with([
                [
                    'id' => 300,
                    'name' => 'Item 300',
                    'quantity' => 9,
                ],
            ])
            ->andReturn([
                [
                    'id' => 300,
                    'name' => 'Item 300',
                    'quantity' => 9,
                ],
            ]);
        $response = $this->postJson('/api/custom-materials', [
            'items' => [
                [
                    'id' => 100,
                    'quantity' => 7,
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertExactJson([
                [
                    'id' => 300,
                    'name' => 'Item 300',
                    'quantity' => 9,
                ],
            ]);
    }

    public function test_custom_list_returns_intermediate_crafting_materials(): void
    {
        $service = app(XivApiService::class);

        $materials = $service->getCustomCraftingMaterialList([
            [
                'id' => 100,
                'quantity' => 7,
            ],
        ]);

        $this->assertCount(1, $materials);

        $this->assertSame(200, $materials[0]['id']);
        $this->assertSame('Item 200', $materials[0]['name']);
        $this->assertSame(6, $materials[0]['quantity']);
        $this->assertSame('Blacksmith', $materials[0]['profession']);
    }

    public function test_custom_crafting_materials_endpoint_returns_intermediate_crafts(): void
    {
        $response = $this->postJson('/api/custom-crafting-materials', [
            'items' => [
                [
                    'id' => 100,
                    'quantity' => 7,
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertExactJson([
                [
                    'id' => 200,
                    'name' => 'Item 200',
                    'quantity' => 6,
                    'profession' => 'Blacksmith',
                ],
            ]);
    }

    public function test_custom_list_rejects_item_without_a_crafting_recipe(): void
    {
        $service = app(XivApiService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'No crafting recipe found for item 999.'
        );

        $service->getCustomMaterialList([
            [
                'id' => 999,
                'quantity' => 1,
            ],
        ]);
    }
}
