<?php

use App\Http\Controllers\Api\Resident\HouseInvitationController;
use App\Http\Controllers\Api\Resident\MyHouseController;
use Illuminate\Support\Facades\Route;

Route::get('/houses', [MyHouseController::class, 'index']);
Route::get('/houses/{house}/statement', [MyHouseController::class, 'statement']);

Route::get('/houses/{house}/invitations', [HouseInvitationController::class, 'index']);
Route::post('/houses/{house}/invitations', [HouseInvitationController::class, 'store']);
Route::post('/invitations/{token}/accept', [HouseInvitationController::class, 'accept']);
