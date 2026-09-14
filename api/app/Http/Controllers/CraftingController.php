<?php

namespace App\Http\Controllers;

use App\Services\XivApiService;
use App\Services\MaterialSourceService;
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
            $validated['maxLevel']
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
            $validated['maxLevel']
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
            $validated['maxLevel']
        );

        return $materialSourceService->enrichMaterials($materials);
    }

    private function validateCraftingRequest(Request $request): array
    {
        return $request->validate([
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
            $validated['maxLevel']
        );
    }
}
