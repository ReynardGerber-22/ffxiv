<?php

namespace App\Services;

use App\Models\Item;
use App\Models\MobSpawn;
use Illuminate\Support\Facades\Http;

class XivApiService
{
    private string $baseUrl = 'https://v2.xivapi.com/api';

    public function getRecipes(
        string $job,
        int $minLevel,
        int $maxLevel,
        bool $includeSpecialSources = true
    ): array {
        $rows = $this->searchRecipes([
            '+CraftType.Name="' . $job . '"',
            '+RecipeLevelTable.ClassJobLevel>=' . $minLevel,
            '+RecipeLevelTable.ClassJobLevel<=' . $maxLevel,
            '+RecipeNotebookList=0',
        ]);

        $recipes = [];

        foreach ($rows as $row) {
            $recipe = $this->transformRecipeRow($row);

            if ($recipe['name'] === '') {
                continue;
            }

            /*
         * V1 only includes normal recipes.
         *
         * Star filtering can become configurable
         * in V2.
         */
            if ($recipe['stars'] > 0) {
                continue;
            }

            $recipes[] = $recipe;
        }

        return $includeSpecialSources ? $recipes : $this->withoutSpecialSourceRecipes($recipes, $job);
    }

    private function withoutSpecialSourceRecipes(array $recipes, string $job): array
    {
        $blocked = array_fill_keys(Item::query()
            ->whereHas('dungeonDrops')
            ->whereDoesntHave('gatheringNodes')
            ->whereDoesntHave('fishingSpots')
            ->whereDoesntHave('gilShopItems', fn($query) => $query->where('is_hq', false)->where('price', '>', 0))
            ->whereDoesntHave('mobDrops', fn($query) => $query->whereIn('bnpc_name_id', MobSpawn::query()->select('bnpc_name_id')))
            ->pluck('id')->all(), true);

        // Discover the same ingredient recipes used by material expansion, once per item.
        $pending = [];
        foreach ($recipes as $recipe) {
            foreach ($recipe['ingredients'] as $ingredient) {
                $pending[$ingredient['id']] = true;
            }
        }
        $seen = [];
        $parents = [];
        $craftable = [];
        while ($pending !== []) {
            $ids = array_keys(array_diff_key($pending, $seen));
            if ($ids === []) {
                break;
            }
            $pending = [];
            foreach (array_chunk($ids, 100) as $chunk) {
                $seen += array_fill_keys($chunk, true);
                foreach ($this->findRecipesByItemIds($chunk, $job) as $itemId => $recipe) {
                    // Craftable loot follows its recipe dependencies instead of requiring a drop.
                    $craftable[$itemId] = true;
                    unset($blocked[$itemId]);
                    foreach ($recipe['ingredients'] as $ingredient) {
                        $parents[$ingredient['id']][$itemId] = true;
                        $pending[$ingredient['id']] = true;
                    }
                }
            }
        }

        $rawIds = array_keys(array_diff_key($seen, $craftable, $blocked));
        $blocked += array_fill_keys(app(SpecialSourceService::class)->blockedItemIds($rawIds), true);

        // Propagate restricted raw sources through every crafted ingredient.
        $queue = array_keys($blocked);
        for ($index = 0; $index < count($queue); $index++) {
            foreach ($parents[$queue[$index]] ?? [] as $parent => $_) {
                if (! isset($blocked[$parent])) {
                    $blocked[$parent] = true;
                    $queue[] = $parent;
                }
            }
        }

        return array_values(array_filter($recipes, function (array $recipe) use ($blocked): bool {
            foreach ($recipe['ingredients'] as $ingredient) {
                if (isset($blocked[$ingredient['id']])) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function getMaterialList(
        string $job,
        int $minLevel,
        int $maxLevel,
        bool $includeSpecialSources = true
    ): array {
        $recipes = $this->getRecipes(
            $job,
            $minLevel,
            $maxLevel,
            $includeSpecialSources
        );

        $materials = [];

        foreach ($recipes as $recipe) {
            foreach ($recipe['ingredients'] as $ingredient) {
                $itemId = $ingredient['id'];

                if (isset($materials[$itemId])) {
                    $materials[$itemId]['quantity'] +=
                        $ingredient['quantity'];

                    continue;
                }

                $materials[$itemId] = [
                    'id' => $itemId,
                    'name' => $ingredient['name'],
                    'quantity' => $ingredient['quantity'],
                ];
            }
        }

        return array_values($materials);
    }

    public function getExpandedMaterialList(
        string $job,
        int $minLevel,
        int $maxLevel,
        bool $includeSpecialSources = true
    ): array {
        $materials = $this->getMaterialList(
            $job,
            $minLevel,
            $maxLevel,
            $includeSpecialSources
        );

        return $this->expandMaterials($materials, $job);
    }

    private function expandMaterials(
        array $materials,
        ?string $preferredJob = null
    ): array {
        $pending = [];

        foreach ($materials as $material) {
            $pending[$material['id']] = $material;
        }

        $finalMaterials = [];

        while (! empty($pending)) {
            $itemIds = array_keys($pending);

            $recipes = $this->findRecipesByItemIds(
                $itemIds,
                $preferredJob
            );

            $nextPending = [];

            foreach ($pending as $itemId => $material) {
                if (! isset($recipes[$itemId])) {
                    if (isset($finalMaterials[$itemId])) {
                        $finalMaterials[$itemId]['quantity'] +=
                            $material['quantity'];
                    } else {
                        $finalMaterials[$itemId] = $material;
                    }

                    continue;
                }

                $recipe = $recipes[$itemId];

                $craftsNeeded = (int) ceil(
                    $material['quantity']
                        / $recipe['amountResult']
                );

                foreach ($recipe['ingredients'] as $ingredient) {
                    $ingredientId = $ingredient['id'];

                    $quantity =
                        $ingredient['quantity']
                        * $craftsNeeded;

                    if (isset($nextPending[$ingredientId])) {
                        $nextPending[$ingredientId]['quantity'] +=
                            $quantity;
                    } else {
                        $nextPending[$ingredientId] = [
                            'id' => $ingredientId,
                            'name' => $ingredient['name'],
                            'quantity' => $quantity,
                        ];
                    }
                }
            }

            $pending = $nextPending;
        }

        return array_values($finalMaterials);
    }

    public function getCraftingMaterialList(
        string $job,
        int $minLevel,
        int $maxLevel,
        bool $includeSpecialSources = true
    ): array {
        $materials = $this->getMaterialList(
            $job,
            $minLevel,
            $maxLevel,
            $includeSpecialSources
        );

        return $this->findCraftingMaterials(
            $materials,
            $job
        );
    }

    private function findCraftingMaterials(
        array $materials,
        ?string $preferredJob = null
    ): array {

        /*
         * Materials that still need to be checked
         * to see if they have crafting recipes.
         */
        $pending = [];

        foreach ($materials as $material) {
            $pending[$material['id']] = $material;
        }

        /*
         * Materials that we discover can themselves
         * be crafted.
         */
        $craftingMaterials = [];

        while (! empty($pending)) {
            $itemIds = array_keys($pending);

            $recipes = $this->findRecipesByItemIds(
                $itemIds,
                $preferredJob
            );

            $nextPending = [];

            foreach ($pending as $itemId => $material) {
                /*
                 * If there is no recipe for this material,
                 * it is raw and therefore doesn't belong
                 * in our crafting list.
                 */
                if (! isset($recipes[$itemId])) {
                    continue;
                }

                $recipe = $recipes[$itemId];

                /*
                 * Work out how many crafts are required.
                 */
                $craftsNeeded = (int) ceil(
                    $material['quantity']
                        / $recipe['amountResult']
                );

                /*
                 * Work out how many items those crafts
                 * will actually produce.
                 */
                $quantityProduced =
                    $craftsNeeded
                    * $recipe['amountResult'];

                /*
                 * Record the craftable material.
                 */
                if (isset($craftingMaterials[$itemId])) {
                    $craftingMaterials[$itemId]['quantity'] +=
                        $quantityProduced;
                } else {
                    $craftingMaterials[$itemId] = [
                        'id' => $material['id'],
                        'name' => $material['name'],
                        'quantity' => $quantityProduced,
                        'profession' => match ($recipe['job']) {
                            'Woodworking' => 'Carpenter',
                            'Smithing' => 'Blacksmith',
                            'Armorcraft' => 'Armorer',
                            'Goldsmithing' => 'Goldsmith',
                            'Leatherworking' => 'Leatherworker',
                            'Clothcraft' => 'Weaver',
                            'Alchemy' => 'Alchemist',
                            'Cooking' => 'Culinarian',
                            default => $recipe['job'],
                        },
                    ];
                }

                /*
                 * Continue down another layer so we also
                 * discover craftable ingredients needed
                 * by this material.
                 */
                foreach ($recipe['ingredients'] as $ingredient) {
                    $ingredientId = $ingredient['id'];

                    $quantity =
                        $ingredient['quantity']
                        * $craftsNeeded;

                    if (isset($nextPending[$ingredientId])) {
                        $nextPending[$ingredientId]['quantity'] +=
                            $quantity;
                    } else {
                        $nextPending[$ingredientId] = [
                            'id' => $ingredientId,
                            'name' => $ingredient['name'],
                            'quantity' => $quantity,
                        ];
                    }
                }
            }

            $pending = $nextPending;
        }

        return array_values($craftingMaterials);
    }

    public function findRecipesByItemIds(
        array $itemIds,
        ?string $preferredJob = null
    ): array {
        if (empty($itemIds)) {
            return [];
        }

        $conditions = array_map(
            function ($itemId) {
                return 'ItemResult=' . $itemId;
            },
            $itemIds
        );

        /*
         * Important:
         *
         * We do NOT filter by Stars here.
         *
         * This method is used for recursive ingredient
         * expansion, so we want to find a recipe for an
         * intermediate material even if that recipe is
         * starred.
         */
        $rows = $this->searchRecipes($conditions);

        $recipes = [];

        foreach ($rows as $row) {
            $recipe = $this->transformRecipeRow($row);

            if ($recipe['name'] === '') {
                continue;
            }

            $itemId = $recipe['itemId'];

            /*
             * Use the first recipe found by default.
             */
            if (! isset($recipes[$itemId])) {
                $recipes[$itemId] = $recipe;

                continue;
            }

            /*
             * If multiple recipes exist for the same item,
             * prefer the recipe belonging to the selected job.
             */
            if (
                $preferredJob !== null
                && $recipe['job'] === $preferredJob
            ) {
                $recipes[$itemId] = $recipe;
            }
        }

        return $recipes;
    }

    private function transformRecipeRow(array $row): array
    {
        $fields = $row['fields'];

        $ingredients = [];

        foreach ($fields['Ingredient'] ?? [] as $index => $ingredient) {
            $name = $ingredient['fields']['Name'] ?? '';

            $quantity =
                $fields['AmountIngredient'][$index]
                ?? 0;

            if ($name === '' || $quantity <= 0) {
                continue;
            }

            $ingredients[] = [
                'id' => $ingredient['row_id'],
                'name' => $name,
                'quantity' => $quantity,
            ];
        }

        return [
            'id' => $row['row_id'],

            'itemId' => $fields['ItemResult']['row_id']
                ?? 0,

            'name' => $fields['ItemResult']['fields']['Name']
                ?? '',

            'job' => $fields['CraftType']['fields']['Name']
                ?? '',

            'level' => $fields['RecipeLevelTable']['fields']['ClassJobLevel']
                ?? 0,

            /*
             * Kept for future V2 star filtering.
             */
            'stars' => $fields['RecipeLevelTable']['fields']['Stars']
                ?? 0,

            'amountResult' => $fields['AmountResult']
                ?? 1,

            'ingredients' => $ingredients,
        ];
    }

    private function getRecipeFields(): string
    {
        return implode(',', [
            'ItemResult.Name',
            'RecipeLevelTable.ClassJobLevel',
            'RecipeLevelTable.Stars',
            'CraftType',
            'Ingredient[].Name',
            'AmountIngredient',
            'AmountResult',
        ]);
    }

    private function searchRecipes(array $query): array
    {
        $results = [];
        $cursor = null;

        do {
            $request = [
                'sheets' => 'Recipe',
                'query' => implode(' ', $query),
                'fields' => $this->getRecipeFields(),
                'limit' => 100,
            ];

            if ($cursor !== null) {
                $request['cursor'] = $cursor;
            }

            $response = Http::get(
                $this->baseUrl . '/search',
                $request
            );

            $response->throw();

            $data = $response->json();

            foreach ($data['results'] ?? [] as $row) {
                $results[] = $row;
            }

            $cursor = $data['next'] ?? null;
        } while ($cursor !== null);

        return $results;
    }

    public function getItem(int $itemId): array
    {
        $response = Http::get(
            $this->baseUrl . '/sheet/Item/' . $itemId,
            []
        );

        $response->throw();

        return $response->json();
    }

    public function getItems(array $itemIds): array
    {
        return Http::timeout(60)->retry(3, 1000)->get($this->baseUrl . '/sheet/Item', [
            'rows' => implode(',', $itemIds),
            'fields' => 'Name',
        ])->throw()->json('rows');
    }

    public function findGatheringItemsByItemId(int $itemId): array
    {
        $response = Http::get(
            $this->baseUrl . '/search',
            [
                'sheets' => 'GatheringItem',
                'query' => 'Item=' . $itemId,
                'fields' => 'Item.Name',
                'limit' => 100,
            ]
        );

        $response->throw();

        return $response->json();
    }

    public function getGatheringItem(int $gatheringItemId): array
    {
        $response = Http::get(
            $this->baseUrl . '/sheet/GatheringItem/' . $gatheringItemId
        );

        $response->throw();

        return $response->json();
    }

    public function findGatheringItemPoints(int $gatheringItemId): array
    {
        $response = Http::get(
            $this->baseUrl . '/search',
            [
                'sheets' => 'GatheringItemPoint',
                'query' => 'GatheringItem=' . $gatheringItemId,
                'limit' => 100,
            ]
        );

        $response->throw();

        return $response->json();
    }

    public function getGatheringPointBase(int $id): array
    {
        $response = Http::get(
            $this->baseUrl . '/sheet/GatheringPointBase/' . $id
        );

        $response->throw();

        return $response->json();
    }

    public function getGatheringPointBases(): array
    {
        $response = Http::get(
            $this->baseUrl . '/sheet/GatheringPointBase',
            [
                'fields' => implode(',', [
                    'GatheringLevel',
                    'GatheringType.Name',
                    'Item@as(raw)',
                ]),
                'limit' => 10,
            ]
        );

        $response->throw();

        return $response->json();
    }

    public function findGatheringPointBasesByGatheringItemId(
        int $gatheringItemId
    ): array {
        $response = Http::get(
            $this->baseUrl . '/search',
            [
                'sheets' => 'GatheringPointBase',
                'query' => 'Item[]=' . $gatheringItemId,
                'fields' => implode(',', [
                    'GatheringLevel',
                    'GatheringType.Name',
                    'Item@as(raw)',
                ]),
                'limit' => 100,
            ]
        );

        $response->throw();

        return $response->json();
    }

    public function findGatheringPointsByBaseId(int $baseId): array
    {
        $response = Http::get(
            $this->baseUrl . '/search',
            [
                'sheets' => 'GatheringPoint',
                'query' => 'GatheringPointBase=' . $baseId,
                'fields' => implode(',', [
                    'GatheringPointBase@as(raw)',
                    'PlaceName.Name',
                    'TerritoryType.PlaceName.Name',
                    'TerritoryType.Map@as(raw)',
                ]),
                'limit' => 100,
            ]
        );

        $response->throw();

        return $response->json();
    }

    public function getGatheringPoint(int $id): array
    {
        $response = Http::get(
            $this->baseUrl . '/sheet/GatheringPoint/' . $id,
            [
                'fields' => implode(',', [
                    'GatheringPointBase@as(raw)',
                    'PlaceName.Name',
                    'TerritoryType@as(raw)',
                    'TerritoryType.PlaceName.Name',
                    'TerritoryType.Map@as(raw)',
                ]),
            ]
        );

        $response->throw();

        return $response->json();
    }

    public function getMap(int $id): array
    {
        $response = Http::get(
            $this->baseUrl . '/sheet/Map/' . $id,
            [
                'fields' => implode(',', [
                    'PlaceName.Name',
                    'TerritoryType@as(raw)',
                    'SizeFactor',
                    'OffsetX',
                    'OffsetY',
                ]),
            ]
        );

        $response->throw();

        return $response->json();
    }

    public function getGatheringPointPosition(int $id): array
    {
        $response = Http::get(
            $this->baseUrl . '/sheet/GatheringPoint/' . $id,
            [
                'fields' => implode(',', [
                    'X',
                    'Y',
                    'Radius',
                    'PlaceName.Name',
                    'TerritoryType@as(raw)',
                    'GatheringPointBase@as(raw)',
                ]),
            ]
        );

        $response->throw();

        return $response->json();
    }

    public function getExportedGatheringPoints(): array
    {
        $response = Http::get(
            $this->baseUrl . '/sheet/ExportedGatheringPoint',
            [
                'limit' => 5,
            ]
        );

        $response->throw();

        return $response->json();
    }

    public function getExportedGatheringPoint(int $id): array
    {
        $response = Http::get(
            $this->baseUrl . '/sheet/ExportedGatheringPoint/' . $id
        );

        $response->throw();

        return $response->json();
    }

    public function search(string $sheet, string $query, string $fields): array
    {
        return Http::get("{$this->baseUrl}/search", [
            'sheets' => $sheet,
            'query' => $query,
            'fields' => $fields,
        ])->throw()->json();
    }

    public function getSheetRow(
        string $sheet,
        int $rowId,
        string $fields = '*'
    ): array {
        return Http::get(
            "{$this->baseUrl}/sheet/{$sheet}/{$rowId}",
            [
                'fields' => $fields,
            ]
        )->throw()->json();
    }

    public function findFishingSpotsByItemId(int $itemId): array
    {
        $spots = [];

        for ($index = 0; $index < 10; $index++) {
            $response = $this->search(
                'FishingSpot',
                "Item[$index]=$itemId",
                'PlaceName.Name'
            );

            foreach ($response['results'] ?? [] as $result) {
                $spots[$result['row_id']] = $result;
            }
        }

        return array_values($spots);
    }

    public function getFishingSpot(int $fishingSpotId): array
    {
        return $this->getSheetRow(
            'FishingSpot',
            $fishingSpotId,
            implode(',', [
                'PlaceName.Name',
                'GatheringLevel',
                'TerritoryType.PlaceName.Name',
                'TerritoryType.Map@as(raw)',
                'X',
                'Z',
                'Radius',
            ])
        );
    }

    /** Yield bounded pages without accumulating the full search result. */
    public function searchPages(string $sheet, string $query, string $fields): \Generator
    {
        $cursor = null;
        $seenCursors = [];
        do {
            $params = ['sheets' => $sheet, 'query' => $query, 'fields' => $fields, 'limit' => 100, 'language' => 'en'];
            if ($cursor !== null) {
                $params['cursor'] = $cursor;
            }
            $data = Http::connectTimeout(15)->timeout(60)->retry(3, 1000)
                ->get($this->baseUrl . '/search', $params)->throw()->json();
            if (! is_array($data) || ! isset($data['results']) || ! is_array($data['results'])) {
                throw new \RuntimeException('Invalid XIVAPI search response for ' . $sheet);
            }
            yield $data['results'];
            $cursor = $data['next'] ?? null;
            if ($cursor !== null) {
                if (! is_string($cursor) || $cursor === '' || isset($seenCursors[$cursor])) {
                    throw new \RuntimeException('Invalid or repeated XIVAPI search cursor for ' . $sheet);
                }
                $seenCursors[$cursor] = true;
            }
        } while ($cursor !== null);
    }

    /** Fetch every search page; callers must use explicit + clauses for AND filters. */
    public function searchAll(string $sheet, string $query, string $fields): array
    {
        $rows = [];
        $cursor = null;
        do {
            $params = ['sheets' => $sheet, 'query' => $query, 'fields' => $fields, 'limit' => 500];
            if ($cursor !== null) {
                $params['cursor'] = $cursor;
            }
            $data = Http::timeout(60)->retry(3, 1000)
                ->get($this->baseUrl . '/search', $params)->throw()->json();
            if (! is_array($data) || ! isset($data['results']) || ! is_array($data['results'])) {
                throw new \RuntimeException('Invalid XIVAPI search response for ' . $sheet);
            }
            array_push($rows, ...($data['results'] ?? []));
            $cursor = $data['next'] ?? null;
        } while ($cursor !== null);

        return $rows;
    }

    public function getSheetRows(string $sheet, array $ids, string $fields): array
    {
        $rows = [];
        foreach (array_chunk(array_values(array_unique($ids)), 100) as $chunk) {
            $data = Http::timeout(60)->retry(3, 1000)->get($this->baseUrl . '/sheet/' . $sheet, [
                'rows' => implode(',', $chunk),
                'fields' => $fields,
            ])->throw()->json();
            if (! is_array($data) || ! isset($data['rows']) || ! is_array($data['rows'])) {
                throw new \RuntimeException('Invalid XIVAPI sheet response for ' . $sheet);
            }
            foreach ($data['rows'] ?? [] as $row) {
                $rows[$row['row_id']] = $row;
            }
        }

        return $rows;
    }

    public function getCustomMaterialList(array $items): array
    {
        $materials = $this->getCustomStartingMaterials($items);

        return $this->expandMaterials($materials);
    }

    private function getCustomStartingMaterials(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $itemIds = array_column($items, 'id');

        $recipes = $this->findRecipesByItemIds($itemIds);

        $materials = [];

        foreach ($items as $item) {
            $itemId = $item['id'];

            if (! isset($recipes[$itemId])) {
                throw new \InvalidArgumentException(
                    "No crafting recipe found for item {$itemId}."
                );
            }

            $recipe = $recipes[$itemId];

            $craftsNeeded = (int) ceil(
                $item['quantity'] / $recipe['amountResult']
            );

            foreach ($recipe['ingredients'] as $ingredient) {
                $ingredientId = $ingredient['id'];

                $quantity =
                    $ingredient['quantity']
                    * $craftsNeeded;

                if (isset($materials[$ingredientId])) {
                    $materials[$ingredientId]['quantity'] += $quantity;

                    continue;
                }

                $materials[$ingredientId] = [
                    'id' => $ingredientId,
                    'name' => $ingredient['name'],
                    'quantity' => $quantity,
                ];
            }
        }

        return array_values($materials);
    }

    public function getCustomCraftingMaterialList(array $items): array
    {
        $materials = $this->getCustomStartingMaterials($items);

        return $this->findCraftingMaterials($materials);
    }

    public function searchCraftableItems(string $search): array
    {
        $rows = $this->searchRecipes([
            'ItemResult.Name~"' . $search . '"',
        ]);

        $items = [];

        foreach ($rows as $row) {
            $recipe = $this->transformRecipeRow($row);

            if ($recipe['itemId'] === 0 || $recipe['name'] === '') {
                continue;
            }

            $items[$recipe['itemId']] = [
                'id' => $recipe['itemId'],
                'name' => $recipe['name'],
                'profession' => match ($recipe['job']) {
                    'Woodworking' => 'Carpenter',
                    'Smithing' => 'Blacksmith',
                    'Armorcraft' => 'Armorer',
                    'Goldsmithing' => 'Goldsmith',
                    'Leatherworking' => 'Leatherworker',
                    'Clothcraft' => 'Weaver',
                    'Alchemy' => 'Alchemist',
                    'Cooking' => 'Culinarian',
                    default => $recipe['job'],
                },
                'level' => $recipe['level'],
                'amountResult' => $recipe['amountResult'],
            ];
        }

        return array_values($items);
    }
}
