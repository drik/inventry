<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryTask;
use App\Models\Media;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(
        protected StorageService $storageService,
    ) {}

    /**
     * Upload media to an inventory item.
     */
    public function uploadToItem(Request $request, string $taskId, string $itemId): JsonResponse
    {
        \Log::info('[Media] uploadToItem called', [
            'taskId' => $taskId,
            'itemId' => $itemId,
            'hasFile' => $request->hasFile('file'),
            'fileValid' => $request->hasFile('file') ? $request->file('file')->isValid() : false,
            'fileOriginalName' => $request->hasFile('file') ? $request->file('file')->getClientOriginalName() : null,
            'fileMime' => $request->hasFile('file') ? $request->file('file')->getClientMimeType() : null,
            'fileSize' => $request->hasFile('file') ? $request->file('file')->getSize() : null,
            'fileError' => $request->hasFile('file') ? $request->file('file')->getError() : null,
            'collection' => $request->input('collection'),
            'contentType' => $request->header('Content-Type'),
        ]);

        $request->validate([
            'file' => 'required|file|max:' . (config('media.max_upload_size_mb', 50) * 1024),
            'collection' => 'required|in:photos,audio,video',
        ]);

        $task = $this->findTask($request, $taskId);

        // Try to find item by ID first, then by asset_id as fallback
        $item = InventoryItem::withoutGlobalScopes()
            ->where('organization_id', $request->user()->organization_id)
            ->where('session_id', $task->session_id)
            ->where(function ($q) use ($itemId) {
                $q->where('id', $itemId)
                    ->orWhere('asset_id', $itemId);
            })
            ->firstOrFail();

        $this->validateMimeType($request->file('file'), $request->input('collection'));

        $org = $request->user()->organization;

        try {
            $media = $this->storageService->upload(
                $org,
                $request->file('file'),
                $request->input('collection'),
                $item,
                $request->user()->id,
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'storage' => $this->storageService->getUsageStats($org),
            ], 422);
        }

        return response()->json([
            'media' => $this->formatMedia($media),
            'storage' => $this->storageService->getUsageStats($org),
        ], 201);
    }

    /**
     * Upload media to a task.
     */
    public function uploadToTask(Request $request, string $taskId): JsonResponse
    {
        \Log::info('[Media] uploadToTask called', [
            'taskId' => $taskId,
            'hasFile' => $request->hasFile('file'),
            'fileValid' => $request->hasFile('file') ? $request->file('file')->isValid() : false,
            'fileOriginalName' => $request->hasFile('file') ? $request->file('file')->getClientOriginalName() : null,
            'fileMime' => $request->hasFile('file') ? $request->file('file')->getClientMimeType() : null,
            'fileSize' => $request->hasFile('file') ? $request->file('file')->getSize() : null,
            'fileError' => $request->hasFile('file') ? $request->file('file')->getError() : null,
            'collection' => $request->input('collection'),
            'contentType' => $request->header('Content-Type'),
        ]);

        $request->validate([
            'file' => 'required|file|max:' . (config('media.max_upload_size_mb', 50) * 1024),
            'collection' => 'required|in:photos,audio,video',
        ]);

        $task = $this->findTask($request, $taskId);

        $this->validateMimeType($request->file('file'), $request->input('collection'));

        $org = $request->user()->organization;

        try {
            $media = $this->storageService->upload(
                $org,
                $request->file('file'),
                $request->input('collection'),
                $task,
                $request->user()->id,
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'storage' => $this->storageService->getUsageStats($org),
            ], 422);
        }

        return response()->json([
            'media' => $this->formatMedia($media),
            'storage' => $this->storageService->getUsageStats($org),
        ], 201);
    }

    /**
     * Get signed URL for a media.
     */
    public function show(Request $request, string $mediaId): JsonResponse
    {
        $media = Media::where('organization_id', $request->user()->organization_id)
            ->findOrFail($mediaId);

        return response()->json([
            'media' => $this->formatMedia($media),
        ]);
    }

    /**
     * Download a media (returns signed URL).
     */
    public function download(Request $request, string $mediaId): JsonResponse
    {
        $media = Media::where('organization_id', $request->user()->organization_id)
            ->findOrFail($mediaId);

        return response()->json([
            'url' => $this->storageService->getSignedUrl($media),
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
        ]);
    }

    /**
     * Delete a media.
     */
    public function destroy(Request $request, string $mediaId): JsonResponse
    {
        $media = Media::where('organization_id', $request->user()->organization_id)
            ->findOrFail($mediaId);

        $this->storageService->delete($media);

        return response()->json(['message' => 'Média supprimé.']);
    }

    protected function validateMimeType($file, string $collection): void
    {
        $allowedMimes = match ($collection) {
            'photos' => config('media.image_mimes', []),
            'audio' => config('media.audio_mimes', []),
            'video' => config('media.video_mimes', []),
            default => [],
        };

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, $allowedMimes)) {
            abort(422, "Type de fichier non autorisé pour la collection '{$collection}'. Extensions acceptées : " . implode(', ', $allowedMimes));
        }
    }

    protected function formatMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'collection' => $media->collection,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size_bytes' => $media->size_bytes,
            'url' => $media->url,
            'metadata' => $media->metadata ?? [],
            'created_at' => $media->created_at->toIso8601String(),
        ];
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
