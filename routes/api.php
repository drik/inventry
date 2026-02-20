<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Tasks
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/{taskId}/download', [TaskController::class, 'download']);
    Route::post('/tasks/{taskId}/start', [TaskController::class, 'start']);
    Route::post('/tasks/{taskId}/complete', [TaskController::class, 'complete']);
    Route::post('/tasks/{taskId}/scan', [TaskController::class, 'scan']);
    Route::post('/tasks/{taskId}/unexpected', [TaskController::class, 'unexpected']);

    // Sync
    Route::post('/tasks/{taskId}/sync', [SyncController::class, 'sync']);
    Route::get('/tasks/{taskId}/sync-status', [SyncController::class, 'status']);
});
