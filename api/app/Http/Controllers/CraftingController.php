<?php

namespace App\Http\Controllers;

use App\Services\MaterialSourceService;
use App\Services\XivApiService;
use Illuminate\Http\Request;

class CraftingController extends Controller
{
    private array $jobMap = [
        'Carpenter' => 'Woodworking',
        'Blacksmith' => 'Smithing',
        'Armorer' => 'Armorcraft',
        'Goldsmith' => 'Goldsmithing',
        'Leatherworker' => 'Leatherworking',
        'Weaver' => 'Clothcraft',
        'Alchemist' => 'Alchemy',
        'Culinarian' => 'Cooking',
    ];

    public function recipes(
        Request $request,
        XivApiService $xivApi
    ): array {
        $validated = $this->validateCraftingRequest($request);

        return $xivApi->getRecipes(
            $this->jobMap[$validated['job']],
            $validated['minLevel'],
            $validated['maxLevel'],
            (bool) ($validated['includeSpecialSources'] ?? $validated['includeDungeonDrops'] ?? true)
        );
    }

    public function materials(
        Request $request,
        XivApiService $xivApi
    ): array {
        $validated = $this->validateCraftingRequest($request);

        return $xivApi->getMaterialList(
            $this->jobMap[$validated['job']],
            $validated['minLevel'],
            $validated['maxLevel'],
            (bool) ($validated['includeSpecialSources'] ?? $validated['includeDungeonDrops'] ?? true)
        );
    }

    public function expandedMaterials(
        Request $request,
        XivApiService $xivApi,
        MaterialSourceService $materialSourceService
    ): array {
        $validated = $this->validateCraftingRequest($request);

        $materials = $xivApi->getExpandedMaterialList(
            $this->jobMap[$validated['job']],
            $validated['minLevel'],
            $validated['maxLevel'],
            (bool) ($validated['includeSpecialSources'] ?? $validated['includeDungeonDrops'] ?? true)
        );

        return $materialSourceService->enrichMaterials($materials);
    }

    private function validateCraftingRequest(Request $request): array
    {
        return $request->validate([
            'includeDungeonDrops' => ['sometimes', 'boolean'],
            'includeSpecialSources' => ['sometimes', 'boolean'],
            'job' => [
                'required',
                'string',
                'in:Carpenter,Blacksmith,Armorer,Goldsmith,Leatherworker,Weaver,Alchemist,Culinarian',
            ],
            'minLevel' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],
            'maxLevel' => [
                'required',
                'integer',
                'min:1',
                'max:100',
                'gte:minLevel',
            ],
        ]);
    }

    public function craftingMaterials(
        Request $request,
        XivApiService $xivApi
    ): array {
        $validated = $this->validateCraftingRequest($request);

        return $xivApi->getCraftingMaterialList(
            $this->jobMap[$validated['job']],
            $validated['minLevel'],
            $validated['maxLevel'],
            (bool) ($validated['includeSpecialSources'] ?? $validated['includeDungeonDrops'] ?? true)
        );
    }
    public function customMaterials(
        Request $request,
        XivApiService $xivApi,
        MaterialSourceService $materialSourceService
    ): array {
        $validated = $request->validate([
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.id' => [
                'required',
                'integer',
                'min:1',
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $materials = $xivApi->getCustomMaterialList(
            $validated['items']
        );

        return $materialSourceService->enrichMaterials($materials);
    }

    public function customCraftingMaterials(
        Request $request,
        XivApiService $xivApi
    ): array {
        $validated = $request->validate([
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.id' => [
                'required',
                'integer',
                'min:1',
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        return $xivApi->getCustomCraftingMaterialList(
            $validated['items']
        );
    }

    public function searchCraftableItems(
        Request $request,
        XivApiService $xivApi
    ): array {
        $validated = $request->validate([
            'search' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],
        ]);

        return $xivApi->searchCraftableItems(
            $validated['search']
        );
    }
}
