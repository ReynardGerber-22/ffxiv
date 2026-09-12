<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class XivApiService
{
    private string $baseUrl = 'https://v2.xivapi.com/api';

    public function getRecipes(
        string $job,
        int $minLevel,
        int $maxLevel
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

            $recipes[] = $recipe;
        }

        return $recipes;
    }
    public function getMaterialList(
        string $job,
        int $minLevel,
        int $maxLevel
    ): array {
        $recipes = $this->getRecipes(
            $job,
            $minLevel,
            $maxLevel
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
        int $maxLevel
    ): array {
        $materials = $this->getMaterialList(
            $job,
            $minLevel,
            $maxLevel
        );

        /*
         * Materials that still need to be checked
         * to see if they have crafting recipes.
         */
        $pending = [];

        foreach ($materials as $material) {
            $pending[$material['id']] = $material;
        }

        /*
         * Materials that cannot be crafted any further.
         */
        $finalMaterials = [];

        while (!empty($pending)) {
            $itemIds = array_keys($pending);

            /*
             * Find recipes for the entire current layer
             * in one XIVAPI request.
             */
            $recipes = $this->findRecipesByItemIds(
                $itemIds,
                $job
            );

            $nextPending = [];

            foreach ($pending as $itemId => $material) {
                /*
                 * No recipe found.
                 *
                 * This means we have reached a raw/final
                 * material.
                 */
                if (!isset($recipes[$itemId])) {
                    if (isset($finalMaterials[$itemId])) {
                        $finalMaterials[$itemId]['quantity'] +=
                            $material['quantity'];
                    } else {
                        $finalMaterials[$itemId] = $material;
                    }

                    continue;
                }

                $recipe = $recipes[$itemId];

                /*
                 * Some recipes produce more than one item
                 * per craft.
                 */
                $craftsNeeded = (int) ceil(
                    $material['quantity']
                        / $recipe['amountResult']
                );

                foreach ($recipe['ingredients'] as $ingredient) {
                    $ingredientId = $ingredient['id'];

                    $quantity =
                        $ingredient['quantity']
                        * $craftsNeeded;

                    /*
                     * If another recipe in this layer needs
                     * the same ingredient, combine them before
                     * processing the next layer.
                     */
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

    public function findRecipesByItemIds(
        array $itemIds,
        string $preferredJob
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

        $rows = $this->searchRecipes($conditions);

        $recipes = [];

        foreach ($rows as $row) {
            $recipe = $this->transformRecipeRow($row);

            if ($recipe['name'] === '') {
                continue;
            }

            $itemId = $recipe['itemId'];

            if (!isset($recipes[$itemId])) {
                $recipes[$itemId] = $recipe;
                continue;
            }

            if ($recipe['job'] === $preferredJob) {
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

            'itemId' =>
            $fields['ItemResult']['row_id']
                ?? 0,

            'name' =>
            $fields['ItemResult']['fields']['Name']
                ?? '',

            'job' =>
            $fields['CraftType']['fields']['Name']
                ?? '',

            'level' =>
            $fields['RecipeLevelTable']['fields']['ClassJobLevel']
                ?? 0,

            'amountResult' =>
            $fields['AmountResult']
                ?? 1,

            'ingredients' => $ingredients,
        ];
    }

    private function getRecipeFields(): string
    {
        return implode(',', [
            'ItemResult.Name',
            'RecipeLevelTable.ClassJobLevel',
            'RecipeLevelTable.ConditionsFlag',
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

    public function getCraftingMaterialList(
        string $job,
        int $minLevel,
        int $maxLevel
    ): array {
        $materials = $this->getMaterialList(
            $job,
            $minLevel,
            $maxLevel
        );

        $pending = [];

        foreach ($materials as $material) {
            $pending[$material['id']] = $material;
        }

        /*
     * Materials that we discover can themselves
     * be crafted.
     */
        $craftingMaterials = [];

        while (!empty($pending)) {
            $itemIds = array_keys($pending);

            $recipes = $this->findRecipesByItemIds(
                $itemIds,
                $job
            );

            $nextPending = [];

            foreach ($pending as $itemId => $material) {
                /*
             * If there is no recipe for this material,
             * it's raw and therefore doesn't belong
             * in our crafting list.
             */
                if (!isset($recipes[$itemId])) {
                    continue;
                }

                $recipe = $recipes[$itemId];

                $craftsNeeded = (int) ceil(
                    $material['quantity']
                        / $recipe['amountResult']
                );

                $quantityProduced =
                    $craftsNeeded * $recipe['amountResult'];

                if (isset($craftingMaterials[$itemId])) {
                    $craftingMaterials[$itemId]['quantity'] +=
                        $quantityProduced;
                } else {
                    $craftingMaterials[$itemId] = [
                        'id' => $material['id'],
                        'name' => $material['name'],
                        'quantity' => $quantityProduced,
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
}
