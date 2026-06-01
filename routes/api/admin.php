<?php

use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\CatalogController;
use App\Http\Controllers\Api\Admin\CatalogItemController;
use App\Http\Controllers\Api\Admin\CondominiumAdminController;
use App\Http\Controllers\Api\Admin\CondominiumCatalogItemController;
use App\Http\Controllers\Api\Admin\CondominiumController;
use App\Http\Controllers\Api\Admin\CondominiumFeeRateController;
use App\Http\Controllers\Api\Admin\CondominiumPaymentMethodController;
use App\Http\Controllers\Api\Admin\CustomFieldController;
use App\Http\Controllers\Api\Admin\FeeChargeController;
use App\Http\Controllers\Api\Admin\HouseController;
use App\Http\Controllers\Api\Admin\HousePaymentController;
use App\Http\Controllers\Api\Admin\MenuController;
use App\Http\Controllers\Api\Admin\PaymentController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\ResidentController;
use App\Http\Controllers\Api\Admin\RoleController;
use Illuminate\Support\Facades\Route;

Route::apiResource('condominiums', CondominiumController::class);
Route::get('/audit-logs', [AuditLogController::class, 'index']);
Route::get('/condominium-admins', [CondominiumAdminController::class, 'all']);
Route::get('/condominiums/{condominium}/admins', [CondominiumAdminController::class, 'index']);
Route::post('/condominiums/{condominium}/admins', [CondominiumAdminController::class, 'store']);
Route::patch('/condominiums/{condominium}/admins/{admin}', [CondominiumAdminController::class, 'update']);
Route::delete('/condominiums/{condominium}/admins/{admin}', [CondominiumAdminController::class, 'destroy']);
Route::get('/condominiums/{condominium}/catalog-items', [CondominiumCatalogItemController::class, 'index']);
Route::post('/condominiums/{condominium}/catalog-items', [CondominiumCatalogItemController::class, 'store']);
Route::get('/condominiums/{condominium}/payment-methods', [CondominiumPaymentMethodController::class, 'index']);
Route::post('/condominiums/{condominium}/payment-methods', [CondominiumPaymentMethodController::class, 'store']);
Route::get('/condominiums/{condominium}/custom-fields', [CustomFieldController::class, 'index']);
Route::post('/condominiums/{condominium}/custom-fields', [CustomFieldController::class, 'store']);
Route::patch('/condominium-payment-methods/{condominiumPaymentMethod}', [CondominiumPaymentMethodController::class, 'update']);
Route::get('/houses/{house}/payments', [HousePaymentController::class, 'index']);
Route::apiResource('houses', HouseController::class)->except(['destroy']);

Route::post('/residents', [ResidentController::class, 'store']);

Route::apiResource('condominium-fee-rates', CondominiumFeeRateController::class)->only(['index', 'store']);
Route::post('/fee-charges/generate-month', [FeeChargeController::class, 'generateMonth']);
Route::apiResource('fee-charges', FeeChargeController::class)->only(['index', 'store']);
Route::apiResource('payments', PaymentController::class)->only(['index', 'store']);
Route::apiResource('menus', MenuController::class)->only(['index', 'store', 'update']);
Route::get('/permissions', [PermissionController::class, 'index']);
Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
Route::apiResource('roles', RoleController::class);

Route::apiResource('catalogs', CatalogController::class)->only(['index', 'show', 'store', 'update']);
Route::post('/catalogs/{catalog}/items', [CatalogItemController::class, 'store']);
Route::patch('/catalog-items/{catalogItem}', [CatalogItemController::class, 'update']);
Route::patch('/custom-fields/{customField}', [CustomFieldController::class, 'update']);
