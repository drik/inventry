<?php

namespace App\Http\Controllers\Api;

use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Models\AiRecognitionLog;
use App\Models\Asset;
use App\Models\InventoryTask;
use App\Services\AiVisionService;
use App\Services\InventoryScanService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiVisionController extends Controller
{
    public function __construct(
        protected AiVisionService $aiVisionService,
        protected InventoryScanService $scanService,
        protected PlanLimitService $planLimitService,
    ) {}

    /**
     * Identify an asset from a photo and find matches.
     * POST /api/tasks/{taskId}/ai-identify
     */
    public function identify(Request $request, string $taskId): JsonResponse
    {
        if (! config('ai-vision.enabled')) {
            return response()->json(['message' => "La fonctionnalité IA Vision n'est pas activée."], 503);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png|max:'.(config('ai-vision.limits.max_image_size_kb', 2048)),
        ]);

        $task = $this->findTask($request, $taskId);
        $org = $request->user()->organization;

        // Check monthly quota (daily is checked by middleware)
        if (! $this->planLimitService->canCreate($org, PlanFeature::MaxAiRequestsMonthly)) {
            return response()->json([
                'message' => $this->planLimitService->getLimitReachedMessage(PlanFeature::MaxAiRequestsMonthly, $org),
                'error' => 'plan_limit_reached',
                'feature' => 'max_ai_requests_monthly',
            ], 403);
        }

        // Store the captured photo
        $imagePath = $this->aiVisionService->storeCapturedPhoto($org, $request->file('photo'));

        // Analyze the photo
        $result = $this->aiVisionService->analyzePhoto(
            imagePath: storage_path('app/'.$imagePath),
            organization: $org,
            locationId: $task->location_id,
            taskId: $task->id,
        );

        // Build match details with asset info
        $matchDetails = [];
        if (! empty($result['matches'])) {
            $assetIds = array_map(fn ($m) => $m->assetId, $result['matches']);
            $assets = Asset::withoutGlobalScopes()
                ->whereIn('id', $assetIds)
                ->with(['category', 'location', 'primaryImage'])
                ->get()
                ->keyBy('id');

            // Get inventory status for these assets
            $session = $task->session;
            $itemStatuses = $session->items()->withoutGlobalScopes()
                ->whereIn('asset_id', $assetIds)
                ->get()
                ->keyBy('asset_id');

            foreach ($result['matches'] as $match) {
                $asset = $assets->get($match->assetId);
                if (! $asset) {
                    continue;
                }

                $item = $itemStatuses->get($match->assetId);

                $matchDetails[] = [
                    'asset_id' => $asset->id,
                    'asset_name' => $asset->name,
                    'asset_code' => $asset->asset_code,
                    'category_name' => $asset->category?->name,
                    'location_name' => $asset->location?->name,
                    'primary_image_url' => $asset->primaryImage?->file_path
                        ? asset('storage/'.$asset->primaryImage->file_path)
                        : null,
                    'confidence' => $match->confidence,
                    'reasoning' => $match->reasoning,
                    'inventory_status' => $item?->status?->value ?? 'not_in_session',
                ];
            }
        }

        // Get usage stats
        $usage = $this->aiVisionService->getUsageStats($org);

        return response()->json([
            'recognition_log_id' => $result['recognition_log_id'],
            'identification' => $result['identification']->toArray(),
            'matches' => $matchDetails,
            'has_strong_match' => $result['has_strong_match'],
            'usage' => $usage,
        ]);
    }

    /**
     * Verify that a photo matches a specific asset.
     * POST /api/tasks/{taskId}/ai-verify
     */
    public function verify(Request $request, string $taskId): JsonResponse
    {
        if (! config('ai-vision.enabled')) {
            return response()->json(['message' => "La fonctionnalité IA Vision n'est pas activée."], 503);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png|max:'.(config('ai-vision.limits.max_image_size_kb', 2048)),
            'asset_id' => 'required|string',
        ]);

        $task = $this->findTask($request, $taskId);
        $org = $request->user()->organization;

        // Check monthly quota
        if (! $this->planLimitService->canCreate($org, PlanFeature::MaxAiRequestsMonthly)) {
            return response()->json([
                'message' => $this->planLimitService->getLimitReachedMessage(PlanFeature::MaxAiRequestsMonthly, $org),
                'error' => 'plan_limit_reached',
                'feature' => 'max_ai_requests_monthly',
            ], 403);
        }

        $asset = Asset::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->findOrFail($request->input('asset_id'));

        // Store the captured photo
        $imagePath = $this->aiVisionService->storeCapturedPhoto($org, $request->file('photo'));

        // Verify identity
        $result = $this->aiVisionService->verifyAssetIdentity(
            capturedImagePath: storage_path('app/'.$imagePath),
            asset: $asset,
            organization: $org,
            taskId: $task->id,
        );

        $usage = $this->aiVisionService->getUsageStats($org);

        return response()->json([
            'recognition_log_id' => $result['recognition_log_id'],
            'is_match' => $result['verification']->isMatch,
            'confidence' => $result['verification']->confidence,
            'reasoning' => $result['verification']->reasoning,
            'discrepancies' => $result['verification']->discrepancies,
            'usage' => $usage,
        ]);
    }

    /**
     * Confirm or reject an AI suggestion.
     * POST /api/tasks/{taskId}/ai-confirm
     */
    public function confirm(Request $request, string $taskId): JsonResponse
    {
        $request->validate([
            'recognition_log_id' => 'required|string',
            'asset_id' => 'required_unless:action,dismissed|string',
            'action' => 'required|string|in:matched,unexpected,dismissed',
        ]);

        $task = $this->findTask($request, $taskId);
        $org = $request->user()->organization;
        $userId = $request->user()->id;

        $log = AiRecognitionLog::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->findOrFail($request->input('recognition_log_id'));

        $action = $request->input('action');
        $assetId = $request->input('asset_id');

        // Update the recognition log with the user's decision
        $log->update([
            'selected_asset_id' => $assetId,
            'selected_action' => $action,
        ]);

        if ($action === 'dismissed') {
            return response()->json([
                'action' => 'dismissed',
                'message' => 'Suggestion IA annulée.',
            ]);
        }

        $asset = Asset::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->findOrFail($assetId);

        $session = $task->session;

        // Find confidence from the log
        $confidence = null;
        $matches = $log->ai_response['matches'] ?? [];
        foreach ($matches as $match) {
            $matchedIds = $log->matched_asset_ids ?? [];
            $refKey = $match['reference_key'] ?? '';
            // Find the confidence for this specific asset
            $refIndex = (int) str_replace('ref_', '', $refKey) - 1;
            if (isset($matchedIds[$refIndex]) && $matchedIds[$refIndex] === $assetId) {
                $confidence = $match['confidence'] ?? null;
                break;
            }
        }

        if ($action === 'matched') {
            // Find existing item for this asset in the session
            $item = $session->items()->withoutGlobalScopes()
                ->where('asset_id', $asset->id)
                ->first();

            if ($item) {
                $this->scanService->markItemFound(
                    item: $item,
                    task: $task,
                    userId: $userId,
                    identificationMethod: 'ai_vision',
                    aiRecognitionLogId: $log->id,
                    aiConfidence: $confidence,
                );
            } else {
                // Asset not in session → add as unexpected but mark as found via AI
                $item = $session->items()->create([
                    'organization_id' => $session->organization_id,
                    'asset_id' => $asset->id,
                    'task_id' => $task->id,
                    'status' => \App\Enums\InventoryItemStatus::Found,
                    'scanned_at' => now(),
                    'scanned_by' => $userId,
                    'identification_method' => 'ai_vision',
                    'ai_recognition_log_id' => $log->id,
                    'ai_confidence' => $confidence,
                ]);
                $this->scanService->refreshSessionCounters($session);
            }

            return response()->json([
                'action' => 'matched',
                'item' => [
                    'id' => $item->id,
                    'asset_id' => $item->asset_id,
                    'status' => $item->status->value ?? $item->status,
                    'scanned_at' => $item->scanned_at?->toIso8601String(),
                    'identification_method' => $item->identification_method,
                ],
            ]);
        }

        if ($action === 'unexpected') {
            $item = $this->scanService->addUnexpected(
                asset: $asset,
                session: $session,
                task: $task,
                userId: $userId,
                identificationMethod: 'ai_vision',
                aiRecognitionLogId: $log->id,
                aiConfidence: $confidence,
            );

            if (! $item) {
                return response()->json(['message' => 'Cet asset est déjà dans la session.'], 422);
            }

            return response()->json([
                'action' => 'unexpected',
                'item' => [
                    'id' => $item->id,
                    'asset_id' => $item->asset_id,
                    'status' => $item->status->value,
                    'scanned_at' => $item->scanned_at->toIso8601String(),
                    'identification_method' => $item->identification_method,
                ],
            ], 201);
        }

        return response()->json(['message' => 'Action non reconnue.'], 422);
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
