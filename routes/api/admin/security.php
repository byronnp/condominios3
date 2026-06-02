<?php

use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('/permissions', [PermissionController::class, 'index']);
Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
Route::apiResource('roles', RoleController::class);
