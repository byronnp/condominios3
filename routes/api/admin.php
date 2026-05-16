<?php

use App\Http\Controllers\Api\Admin\CatalogController;
use App\Http\Controllers\Api\Admin\CatalogItemController;
use App\Http\Controllers\Api\Admin\CondominiumController;
use App\Http\Controllers\Api\Admin\CondominiumAdminController;
use App\Http\Controllers\Api\Admin\CondominiumCatalogItemController;
use App\Http\Controllers\Api\Admin\CustomFieldController;
use App\Http\Controllers\Api\Admin\FeeChargeController;
use App\Http\Controllers\Api\Admin\HouseController;
use App\Http\Controllers\Api\Admin\PaymentController;
use App\Http\Controllers\Api\Admin\ResidentController;
use Illuminate\Support\Facades\Route;

Route::apiResource('condominiums', CondominiumController::class)->except(['destroy']);
Route::get('/condominiums/{condominium}/admins', [CondominiumAdminController::class, 'index']);
Route::post('/condominiums/{condominium}/admins', [CondominiumAdminController::class, 'store']);
Route::get('/condominiums/{condominium}/catalog-items', [CondominiumCatalogItemController::class, 'index']);
Route::post('/condominiums/{condominium}/catalog-items', [CondominiumCatalogItemController::class, 'store']);
Route::get('/condominiums/{condominium}/custom-fields', [CustomFieldController::class, 'index']);
Route::post('/condominiums/{condominium}/custom-fields', [CustomFieldController::class, 'store']);
Route::apiResource('houses', HouseController::class)->except(['destroy']);

Route::post('/residents', [ResidentController::class, 'store']);

Route::apiResource('fee-charges', FeeChargeController::class)->only(['index', 'store']);
Route::apiResource('payments', PaymentController::class)->only(['index', 'store']);

Route::apiResource('catalogs', CatalogController::class)->only(['index', 'store', 'update']);
Route::post('/catalogs/{catalog}/items', [CatalogItemController::class, 'store']);
Route::patch('/catalog-items/{catalogItem}', [CatalogItemController::class, 'update']);
Route::patch('/custom-fields/{customField}', [CustomFieldController::class, 'update']);
