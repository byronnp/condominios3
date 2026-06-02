<?php

use App\Http\Controllers\Api\Admin\ResidentController;
use Illuminate\Support\Facades\Route;

Route::post('/residents', [ResidentController::class, 'store']);
