<?php

use App\Http\Controllers\Api\Admin\CondominiumFeeRateController;
use App\Http\Controllers\Api\Admin\CondominiumPaymentMethodController;
use App\Http\Controllers\Api\Admin\FeeChargeController;
use App\Http\Controllers\Api\Admin\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/condominiums/{condominium}/payment-methods', [CondominiumPaymentMethodController::class, 'index']);
Route::post('/condominiums/{condominium}/payment-methods', [CondominiumPaymentMethodController::class, 'store']);
Route::patch('/condominium-payment-methods/{condominiumPaymentMethod}', [CondominiumPaymentMethodController::class, 'update']);

Route::apiResource('condominium-fee-rates', CondominiumFeeRateController::class)->only(['index', 'store']);
Route::post('/fee-charges/generate-month', [FeeChargeController::class, 'generateMonth']);
Route::apiResource('fee-charges', FeeChargeController::class)->only(['index', 'store']);
Route::apiResource('payments', PaymentController::class)->only(['index', 'store']);
