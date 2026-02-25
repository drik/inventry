<?php

use App\Http\Controllers\Api\AiAssistantController;
use App\Http\Controllers\Api\AiVisionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConditionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ItemStatusController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SubscriptionController;
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

    // Subscription
    Route::get('/subscription/current', [SubscriptionController::class, 'current']);
    Route::get('/subscription/plans', [SubscriptionController::class, 'plans']);
    Route::get('/subscription/usage', [SubscriptionController::class, 'usage']);

    // Tasks
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/{taskId}/download', [TaskController::class, 'download']);
    Route::post('/tasks/{taskId}/start', [TaskController::class, 'start']);
    Route::post('/tasks/{taskId}/complete', [TaskController::class, 'complete']);
    Route::post('/tasks/{taskId}/scan', [TaskController::class, 'scan'])->middleware('plan.limit:max_assets');
    Route::post('/tasks/{taskId}/unexpected', [TaskController::class, 'unexpected']);

    // Media (on items and tasks)
    Route::post('/tasks/{taskId}/items/{itemId}/media', [MediaController::class, 'uploadToItem']);
    Route::post('/tasks/{taskId}/media', [MediaController::class, 'uploadToTask']);
    Route::get('/media/{mediaId}', [MediaController::class, 'show']);
    Route::get('/media/{mediaId}/download', [MediaController::class, 'download']);
    Route::delete('/media/{mediaId}', [MediaController::class, 'destroy']);

    // Conditions
    Route::get('/conditions', [ConditionController::class, 'index']);
    Route::put('/tasks/{taskId}/items/{itemId}/condition', [ConditionController::class, 'updateItemCondition']);

    // Item status
    Route::put('/tasks/{taskId}/items/{itemId}/status', [ItemStatusController::class, 'update']);

    // Notes (on items and tasks)
    Route::post('/tasks/{taskId}/items/{itemId}/notes', [NoteController::class, 'storeForItem']);
    Route::get('/tasks/{taskId}/items/{itemId}/notes', [NoteController::class, 'indexForItem']);
    Route::post('/tasks/{taskId}/notes', [NoteController::class, 'storeForTask']);
    Route::get('/tasks/{taskId}/notes', [NoteController::class, 'indexForTask']);
    Route::delete('/notes/{noteId}', [NoteController::class, 'destroy']);

    // AI Vision
    Route::get('/ai-usage', [AiVisionController::class, 'usage']);
    Route::post('/tasks/{taskId}/ai-identify', [AiVisionController::class, 'identify'])
        ->middleware(['throttle:ai-vision', 'plan.limit:max_ai_requests_daily']);
    Route::post('/tasks/{taskId}/ai-verify', [AiVisionController::class, 'verify'])
        ->middleware(['throttle:ai-vision', 'plan.limit:max_ai_requests_daily']);
    Route::post('/tasks/{taskId}/ai-confirm', [AiVisionController::class, 'confirm']);

    // AI Assistant (rephrase, describe, transcribe)
    Route::post('/tasks/{taskId}/ai-rephrase', [AiAssistantController::class, 'rephrase'])
        ->middleware(['throttle:ai-vision', 'plan.limit:max_ai_requests_daily']);
    Route::post('/tasks/{taskId}/ai-describe-photo', [AiAssistantController::class, 'describePhoto'])
        ->middleware(['throttle:ai-vision', 'plan.limit:max_ai_requests_daily']);
    Route::post('/tasks/{taskId}/ai-transcribe', [AiAssistantController::class, 'transcribe'])
        ->middleware(['throttle:ai-vision', 'plan.limit:max_ai_requests_daily']);
    Route::post('/tasks/{taskId}/ai-describe-video', [AiAssistantController::class, 'describeVideo'])
        ->middleware(['throttle:ai-vision', 'plan.limit:max_ai_requests_daily']);

    // Documents (on assets)
    Route::post('/assets/{assetId}/documents', [DocumentController::class, 'upload']);
    Route::get('/assets/{assetId}/documents', [DocumentController::class, 'index']);

    // Reports
    Route::post('/tasks/{taskId}/report', [ReportController::class, 'generateTaskReport']);
    Route::get('/tasks/{taskId}/report', [ReportController::class, 'showTaskReport']);
    Route::get('/sessions/{sessionId}/report', [ReportController::class, 'showSessionReport']);
    Route::get('/reports/{reportId}/pdf', [ReportController::class, 'pdf']);
    Route::get('/reports/{reportId}/excel', [ReportController::class, 'excel']);

    // Sync
    Route::post('/tasks/{taskId}/sync', [SyncController::class, 'sync']);
    Route::get('/tasks/{taskId}/sync-status', [SyncController::class, 'status']);
});
