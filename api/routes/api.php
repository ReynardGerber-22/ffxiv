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