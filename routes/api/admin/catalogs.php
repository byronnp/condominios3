<?php

use App\Http\Controllers\Api\Admin\CatalogController;
use App\Http\Controllers\Api\Admin\CatalogItemController;
use App\Http\Controllers\Api\Admin\CondominiumCatalogItemController;
use App\Http\Controllers\Api\Admin\CustomFieldController;
use Illuminate\Support\Facades\Route;

Route::get('/condominiums/{condominium}/catalog-items', [CondominiumCatalogItemController::class, 'index']);
Route::post('/condominiums/{condominium}/catalog-items', [CondominiumCatalogItemController::class, 'store']);
Route::get('/condominiums/{condominium}/custom-fields', [CustomFieldController::class, 'index']);
Route::post('/condominiums/{condominium}/custom-fields', [CustomFieldController::class, 'store']);
Route::patch('/custom-fields/{customField}', [CustomFieldController::class, 'update']);

Route::apiResource('catalogs', CatalogController::class)->only(['index', 'show', 'store', 'update']);
Route::post('/catalogs/{catalog}/items', [CatalogItemController::class, 'store']);
Route::patch('/catalog-items/{catalogItem}', [CatalogItemController::class, 'update']);
