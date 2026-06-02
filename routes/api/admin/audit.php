<?php

use App\Http\Controllers\Api\Admin\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('/audit-logs', [AuditLogController::class, 'index']);
