<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetCondition;
use App\Models\InventoryItem;
use App\Models\InventoryTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConditionController extends Controller
{
    /**
     * List all conditions for the user's organization.
     */
    public function index(Request $request): JsonResponse
    {
        $conditions = AssetCondition::withoutGlobalScopes()
            ->where('organization_id', $request->user()->organization_id)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'conditions' => $conditions->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'color' => $c->color,
                'icon' => $c->icon,
                'sort_order' => $c->sort_order,
            ]),
        ]);
    }

    /**
     * Update the condition of an inventory item.
     */
    public function updateItemCondition(Request $request, string $taskId, string $itemId): JsonResponse
    {
        $request->validate([
            'condition_id' => 'required|string',
        ]);

        $task = $this->findTask($request, $taskId);

        $item = InventoryItem::withoutGlobalScopes()
            ->where('organization_id', $request->user()->organization_id)
            ->where('session_id', $task->session_id)
            ->where(fn ($q) => $q->where('id', $itemId)->orWhere('asset_id', $itemId))
            ->firstOrFail();

        // Verify condition belongs to the org
        $condition = AssetCondition::withoutGlobalScopes()
            ->where('organization_id', $request->user()->organization_id)
            ->findOrFail($request->input('condition_id'));

        $item->update(['condition_id' => $condition->id]);

        return response()->json([
            'item' => [
                'id' => $item->id,
                'condition_id' => $item->condition_id,
                'condition_name' => $condition->name,
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
