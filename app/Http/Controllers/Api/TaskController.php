<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\InventoryTask;
use App\Services\InventoryScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        protected InventoryScanService $scanService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $orgId = $request->user()->organization_id;

        $query = InventoryTask::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('assigned_to', $userId)
            ->with(['session', 'location']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Sort: in_progress first, then pending, then completed
        $query->orderByRaw("
            CASE status
                WHEN 'in_progress' THEN 0
                WHEN 'pending' THEN 1
                WHEN 'completed' THEN 2
                ELSE 3
            END
        ")->orderByDesc('created_at');

        $tasks = $query->paginate(15);

        $data = $tasks->map(function (InventoryTask $task) {
            $itemsQuery = $task->session->items()->withoutGlobalScopes();
            if ($task->location_id) {
                $itemsQuery->whereHas('asset', fn ($q) => $q->where('location_id', $task->location_id));
            }

            return [
                'id' => $task->id,
                'session' => [
                    'id' => $task->session->id,
                    'name' => $task->session->name,
                    'status' => $task->session->status->value,
                    'description' => $task->session->description,
                ],
                'location' => $task->location ? [
                    'id' => $task->location->id,
                    'name' => $task->location->name,
                    'city' => $task->location->city,
                ] : null,
                'status' => $task->status,
                'started_at' => $task->started_at?->toIso8601String(),
                'completed_at' => $task->completed_at?->toIso8601String(),
                'notes' => $task->notes,
                'items_count' => (clone $itemsQuery)->count(),
                'scanned_count' => (clone $itemsQuery)->whereNotNull('scanned_at')->count(),
                'created_at' => $task->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    public function download(Request $request, string $taskId): JsonResponse
    {
        $task = $this->findTask($request, $taskId);
        $session = $task->session;
        $orgId = $request->user()->organization_id;

        // Items scoped to task's location
        $itemsQuery = $session->items()->withoutGlobalScopes();
        if ($task->location_id) {
            $itemsQuery->whereHas('asset', fn ($q) => $q->where('location_id', $task->location_id));
        }
        $items = $itemsQuery->get();

        // Full asset details for session items
        $assetIds = $items->pluck('asset_id')->unique()->filter();
        $assets = Asset::withoutGlobalScopes()
            ->whereIn('id', $assetIds)
            ->with(['category', 'location', 'primaryImage', 'tagValues.tag'])
            ->get();

        // All org asset barcodes for offline resolution
        $allBarcodes = Asset::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->with('tagValues')
            ->get(['id', 'barcode', 'asset_code'])
            ->map(fn (Asset $a) => [
                'asset_id' => $a->id,
                'barcode' => $a->barcode,
                'asset_code' => $a->asset_code,
                'tag_values' => $a->tagValues->pluck('value')->toArray(),
            ]);

        return response()->json([
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
                'notes' => $task->notes,
            ],
            'session' => [
                'id' => $session->id,
                'name' => $session->name,
                'status' => $session->status->value,
            ],
            'location' => $task->location ? [
                'id' => $task->location->id,
                'name' => $task->location->name,
                'city' => $task->location->city,
            ] : null,
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'asset_id' => $item->asset_id,
                'status' => $item->status->value,
                'scanned_at' => $item->scanned_at?->toIso8601String(),
                'scanned_by' => $item->scanned_by,
                'condition_notes' => $item->condition_notes,
            ]),
            'assets' => $assets->map(fn (Asset $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'asset_code' => $a->asset_code,
                'barcode' => $a->barcode,
                'serial_number' => $a->serial_number,
                'category_name' => $a->category?->name,
                'location_name' => $a->location?->name,
                'status' => $a->status?->value,
                'primary_image_url' => $a->primaryImage?->file_path
                    ? asset('storage/'.$a->primaryImage->file_path)
                    : null,
                'tag_values' => $a->tagValues->map(fn ($tv) => [
                    'id' => $tv->id,
                    'tag_name' => $tv->tag?->name,
                    'value' => $tv->value,
                    'encoding_mode' => $tv->encoding_mode?->value,
                ]),
            ]),
            'all_asset_barcodes' => $allBarcodes,
            'downloaded_at' => now()->toIso8601String(),
        ]);
    }

    public function start(Request $request, string $taskId): JsonResponse
    {
        $task = $this->findTask($request, $taskId);

        if ($task->status !== 'pending') {
            return response()->json(['message' => 'La tâche ne peut être démarrée que si elle est en attente.'], 422);
        }

        $task->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return response()->json([
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
                'started_at' => $task->started_at->toIso8601String(),
            ],
        ]);
    }

    public function complete(Request $request, string $taskId): JsonResponse
    {
        $task = $this->findTask($request, $taskId);

        if ($task->status !== 'in_progress') {
            return response()->json(['message' => 'La tâche doit être en cours pour être terminée.'], 422);
        }

        $this->scanService->completeTask($task, $request->user()->id);

        $task->refresh();

        return response()->json([
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
                'completed_at' => $task->completed_at->toIso8601String(),
            ],
        ]);
    }

    public function scan(Request $request, string $taskId): JsonResponse
    {
        $request->validate(['barcode' => 'required|string']);

        $task = $this->findTask($request, $taskId);
        $session = $task->session;

        $result = $this->scanService->scanBarcode(
            $request->input('barcode'),
            $session,
            $task,
            $request->user()->id,
        );

        if (! $result['found']) {
            return response()->json([
                'found' => false,
                'message' => $result['message'],
            ], 404);
        }

        $asset = $result['asset'];

        return response()->json([
            'found' => true,
            'is_unexpected' => $result['is_unexpected'],
            'already_scanned' => $result['already_scanned'] ?? false,
            'asset' => [
                'id' => $asset->id,
                'name' => $asset->name,
                'asset_code' => $asset->asset_code,
                'category_name' => $asset->category?->name,
                'primary_image_url' => $asset->primaryImage?->file_path
                    ? asset('storage/'.$asset->primaryImage->file_path)
                    : null,
            ],
            'item' => $result['item'] ? [
                'id' => $result['item']->id,
                'status' => $result['item']->status->value,
                'scanned_at' => $result['item']->scanned_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function unexpected(Request $request, string $taskId): JsonResponse
    {
        $request->validate([
            'asset_id' => 'required|string',
            'condition_notes' => 'nullable|string',
        ]);

        $task = $this->findTask($request, $taskId);
        $asset = Asset::withoutGlobalScopes()
            ->where('organization_id', $request->user()->organization_id)
            ->findOrFail($request->input('asset_id'));

        $item = $this->scanService->addUnexpected(
            $asset,
            $task->session,
            $task,
            $request->user()->id,
            $request->input('condition_notes'),
        );

        if (! $item) {
            return response()->json(['message' => 'Cet asset est déjà dans la session.'], 422);
        }

        return response()->json([
            'item' => [
                'id' => $item->id,
                'asset_id' => $item->asset_id,
                'status' => $item->status->value,
                'scanned_at' => $item->scanned_at->toIso8601String(),
            ],
        ], 201);
    }

    protected function findTask(Request $request, string $taskId): InventoryTask
    {
        $orgId = $request->user()->organization_id;

        $task = InventoryTask::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->with(['session', 'location'])
            ->findOrFail($taskId);

        // Check assignment (user must be assigned, or be Admin/Manager)
        $user = $request->user();
        if ($task->assigned_to !== $user->id && ! $user->hasRoleAtLeast(\App\Enums\UserRole::Manager)) {
            abort(403, 'Cette tâche ne vous est pas assignée.');
        }

        return $task;
    }
}
