<?php

use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(base_path('routes/api/auth.php'));

Route::middleware(['jwt.auth', 'admin.access'])
    ->prefix('admin')
    ->group(base_path('routes/api/admin.php'));

Route::middleware('jwt.auth')
    ->prefix('resident')
    ->group(base_path('routes/api/resident.php'));
