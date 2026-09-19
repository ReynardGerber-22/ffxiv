<?php

use App\Http\Controllers\CraftingController;
use Illuminate\Support\Facades\Route;

Route::get('/recipes', [
    CraftingController::class,
    'recipes',
]);

Route::get('/materials', [
    CraftingController::class,
    'materials',
]);

Route::get('/expanded-materials', [
    CraftingController::class,
    'expandedMaterials',
]);

Route::get('/crafting-materials', [
    CraftingController::class,
    'craftingMaterials',
]);

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
    ]);
});

Route::post(
    '/custom-materials',
    [CraftingController::class, 'customMaterials']
);

Route::post(
    '/custom-crafting-materials',
    [CraftingController::class, 'customCraftingMaterials']
);

Route::get(
    '/craftable-items',
    [CraftingController::class, 'searchCraftableItems']
);
