<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryItemStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryTask;
use App\Services\InventoryScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemStatusController extends Controller
{
    public function __construct(
        protected InventoryScanService $scanService,
    ) {}

    /**
     * Change the status of an inventory item.
     */
    public function update(Request $request, string $taskId, string $itemId): JsonResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(array_column(InventoryItemStatus::cases(), 'value'))],
            'reason' => 'nullable|string|max:1000',
        ]);

        $task = $this->findTask($request, $taskId);

        $item = InventoryItem::withoutGlobalScopes()
            ->where('organization_id', $request->user()->organization_id)
            ->where('session_id', $task->session_id)
            ->where(fn ($q) => $q->where('id', $itemId)->orWhere('asset_id', $itemId))
            ->firstOrFail();

        $newStatus = InventoryItemStatus::from($request->input('status'));

        if ($item->status === $newStatus) {
            return response()->json([
                'message' => 'Le statut est déjà ' . $newStatus->getLabel() . '.',
            ], 422);
        }

        $this->scanService->changeItemStatus(
            $item,
            $newStatus,
            $request->user()->id,
            $request->input('reason'),
        );

        $item->refresh();

        return response()->json([
            'item' => [
                'id' => $item->id,
                'status' => $item->status->value,
                'status_reason' => $request->input('reason'),
                'scanned_at' => $item->scanned_at?->toIso8601String(),
            ],
        ]);
    }

    protected function findTask(Request $request, string $taskId): InventoryTask
    {
        $task = InventoryTask::withoutGlobalScopes()
            ->where('organization_id', $request->user()->organization_id)
            ->with(['session'])
            ->findOrFail($taskId);

        $user = $request->user();
        if ($task->assigned_to !== $user->id && ! $user->hasRoleAtLeast(\App\Enums\UserRole::Manager)) {
            abort(403, 'Cette tâche ne vous est pas assignée.');
        }

        return $task;
    }
}
