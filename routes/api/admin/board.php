<?php

use App\Http\Controllers\Api\Admin\BoardMemberController;
use App\Http\Controllers\Api\Admin\BoardTermController;
use Illuminate\Support\Facades\Route;

Route::get('/condominiums/{condominium}/board-terms', [BoardTermController::class, 'index']);
Route::post('/condominiums/{condominium}/board-terms', [BoardTermController::class, 'store']);
Route::get('/board-terms/{boardTerm}', [BoardTermController::class, 'show']);
Route::patch('/board-terms/{boardTerm}', [BoardTermController::class, 'update']);
Route::post('/board-terms/{boardTerm}/members', [BoardMemberController::class, 'store']);
Route::patch('/board-members/{boardMember}', [BoardMemberController::class, 'update']);
Route::delete('/board-members/{boardMember}', [BoardMemberController::class, 'destroy']);
