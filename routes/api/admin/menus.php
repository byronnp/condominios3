<?php

use App\Http\Controllers\Api\Admin\MenuController;
use Illuminate\Support\Facades\Route;

Route::apiResource('menus', MenuController::class)->only(['index', 'store', 'update']);
