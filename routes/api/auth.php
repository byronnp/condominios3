<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\MenuController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('jwt.auth')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/me/menus', [MenuController::class, 'index']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
