<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventorySessionStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $orgId = $request->user()->organization_id;

        $baseQuery = InventoryTask::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('assigned_to', $userId);

        $pending = (clone $baseQuery)
            ->where('status', 'pending')
            ->whereHas('session', fn ($q) => $q->where('status', InventorySessionStatus::InProgress))
            ->count();

        $inProgress = (clone $baseQuery)->where('status', 'in_progress')->count();

        $completedToday = (clone $baseQuery)
            ->where('status', 'completed')
            ->whereDate('completed_at', today())
            ->count();

        $completedTotal = (clone $baseQuery)->where('status', 'completed')->count();

        // Current task (most recent in_progress)
        $currentTask = (clone $baseQuery)
            ->where('status', 'in_progress')
            ->with(['session', 'location'])
            ->latest('started_at')
            ->first();

        $currentTaskData = null;
        if ($currentTask) {
            $session = $currentTask->session;
            $itemsQuery = $session->items()->withoutGlobalScopes();

            if ($currentTask->location_id) {
                $itemsQuery->whereHas('asset', fn ($q) => $q->where('location_id', $currentTask->location_id));
            }

            $totalExpected = (clone $itemsQuery)->count();
            $totalScanned = (clone $itemsQuery)->whereNotNull('scanned_at')->count();

            $currentTaskData = [
                'id' => $currentTask->id,
                'session_name' => $session->name,
                'location_name' => $currentTask->location?->name,
                'status' => $currentTask->status,
                'progress' => [
                    'total_expected' => $totalExpected,
                    'total_scanned' => $totalScanned,
                    'total_matched' => $session->total_matched,
                    'total_missing' => $session->total_missing,
                    'total_unexpected' => $session->total_unexpected,
                ],
            ];
        }

        return response()->json([
            'stats' => [
                'pending' => $pending,
                'in_progress' => $inProgress,
                'completed_today' => $completedToday,
                'completed_total' => $completedTotal,
            ],
            'current_task' => $currentTaskData,
        ]);
    }
}
