<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryItemStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryTask;
use App\Services\InventoryScanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(
        protected InventoryScanService $scanService,
    ) {}

    public function sync(Request $request, string $taskId): JsonResponse
    {
        $request->validate([
            'scans' => 'present|array',
            'scans.*.item_id' => 'nullable|string',
            'scans.*.asset_id' => 'nullable|string',
            'scans.*.status' => 'required|string|in:found,unexpected',
            'scans.*.scanned_at' => 'required|date',
            'scans.*.condition_notes' => 'nullable|string',
            'task_status' => 'nullable|string|in:pending,in_progress,completed',
            'task_notes' => 'nullable|string',
            'last_synced_at' => 'nullable|date',
        ]);

        $task = $this->findTask($request, $taskId);
        $session = $task->session;
        $userId = $request->user()->id;

        $syncedCount = 0;
        $conflicts = [];

        foreach ($request->input('scans', []) as $scan) {
            if (! empty($scan['item_id'])) {
                // Update existing item
                $item = InventoryItem::withoutGlobalScopes()
                    ->where('organization_id', $request->user()->organization_id)
                    ->find($scan['item_id']);

                if (! $item) {
                    continue;
                }

                $scanTime = Carbon::parse($scan['scanned_at']);

                // Conflict: item already scanned by someone else with a more recent timestamp
                if ($item->scanned_at && $item->scanned_by !== $userId && $item->scanned_at > $scanTime) {
                    $conflicts[] = [
                        'item_id' => $item->id,
                        'reason' => 'already_scanned_by_another_user',
                        'server_scanned_at' => $item->scanned_at->toIso8601String(),
                        'server_scanned_by' => $item->scanner?->name ?? 'Unknown',
                    ];

                    continue;
                }

                $item->update([
                    'status' => $scan['status'] === 'found' ? InventoryItemStatus::Found : InventoryItemStatus::Unexpected,
                    'scanned_at' => $scanTime,
                    'scanned_by' => $userId,
                    'task_id' => $task->id,
                    'condition_notes' => $scan['condition_notes'] ?? $item->condition_notes,
                ]);
                $syncedCount++;
            } elseif (! empty($scan['asset_id'])) {
                // Create unexpected item
                $exists = $session->items()->withoutGlobalScopes()
                    ->where('asset_id', $scan['asset_id'])
                    ->exists();

                if (! $exists) {
                    $session->items()->create([
                        'organization_id' => $session->organization_id,
                        'asset_id' => $scan['asset_id'],
                        'task_id' => $task->id,
                        'status' => InventoryItemStatus::Unexpected,
                        'scanned_at' => Carbon::parse($scan['scanned_at']),
                        'scanned_by' => $userId,
                        'condition_notes' => $scan['condition_notes'] ?? null,
                    ]);
                    $syncedCount++;
                }
            }
        }

        // Update task status/notes
        if ($request->filled('task_notes')) {
            $task->update(['notes' => $request->input('task_notes')]);
        }

        if ($request->input('task_status') === 'in_progress' && $task->status === 'pending') {
            $task->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
        }

        if ($request->input('task_status') === 'completed' && $task->status !== 'completed') {
            $this->scanService->completeTask($task, $userId);
        }

        // Refresh counters
        $this->scanService->refreshSessionCounters($session);
        $task->refresh();

        // Return updated items
        $itemsQuery = $session->items()->withoutGlobalScopes();
        if ($task->location_id) {
            $itemsQuery->whereHas('asset', fn ($q) => $q->where('location_id', $task->location_id));
        }

        return response()->json([
            'synced_count' => $syncedCount,
            'conflicts' => $conflicts,
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
            ],
            'items' => $itemsQuery->get()->map(fn ($item) => [
                'id' => $item->id,
                'asset_id' => $item->asset_id,
                'status' => $item->status->value,
                'scanned_at' => $item->scanned_at?->toIso8601String(),
                'scanned_by' => $item->scanned_by,
                'condition_notes' => $item->condition_notes,
            ]),
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    public function status(Request $request, string $taskId): JsonResponse
    {
        $request->validate(['since' => 'required|date']);

        $task = $this->findTask($request, $taskId);
        $session = $task->session;
        $since = Carbon::parse($request->input('since'));

        $itemsQuery = $session->items()->withoutGlobalScopes();
        if ($task->location_id) {
            $itemsQuery->whereHas('asset', fn ($q) => $q->where('location_id', $task->location_id));
        }

        $changedCount = (clone $itemsQuery)->where('updated_at', '>', $since)->count();
        $lastUpdate = (clone $itemsQuery)->max('updated_at');

        return response()->json([
            'has_changes' => $changedCount > 0,
            'server_updated_at' => $lastUpdate,
            'items_changed' => $changedCount,
        ]);
    }

    protected function findTask(Request $request, string $taskId): InventoryTask
    {
        $orgId = $request->user()->organization_id;

        $task = InventoryTask::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->with(['session', 'location'])
            ->findOrFail($taskId);

        $user = $request->user();
        if ($task->assigned_to !== $user->id && ! $user->hasRoleAtLeast(\App\Enums\UserRole::Manager)) {
            abort(403, 'Cette tâche ne vous est pas assignée.');
        }

        return $task;
    }
}
