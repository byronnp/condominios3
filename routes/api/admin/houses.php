<?php

use App\Http\Controllers\Api\Admin\HouseController;
use App\Http\Controllers\Api\Admin\HousePaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/houses/{house}/payments', [HousePaymentController::class, 'index']);
Route::apiResource('houses', HouseController::class)->except(['destroy']);
