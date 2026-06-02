<?php

use App\Http\Controllers\Api\Admin\CondominiumAdminController;
use App\Http\Controllers\Api\Admin\CondominiumController;
use Illuminate\Support\Facades\Route;

Route::apiResource('condominiums', CondominiumController::class);
Route::get('/condominium-admins', [CondominiumAdminController::class, 'all']);
Route::get('/condominiums/{condominium}/admins', [CondominiumAdminController::class, 'index']);
Route::post('/condominiums/{condominium}/admins', [CondominiumAdminController::class, 'store']);
Route::patch('/condominiums/{condominium}/admins/{admin}', [CondominiumAdminController::class, 'update']);
Route::delete('/condominiums/{condominium}/admins/{admin}', [CondominiumAdminController::class, 'destroy']);
