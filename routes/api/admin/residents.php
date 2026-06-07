<?php

use App\Http\Controllers\Api\Admin\ResidentController;
use Illuminate\Support\Facades\Route;

Route::get('/houses/{house}/residents', [ResidentController::class, 'indexByHouse']);
Route::post('/residents', [ResidentController::class, 'store']);
