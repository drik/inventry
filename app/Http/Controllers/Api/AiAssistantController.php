<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryTask;
use App\Models\Media;
use App\Services\AiAssistantService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAssistantController extends Controller
{
    public function __construct(
        protected AiAssistantService $aiService,
        protected StorageService $storageService,
    ) {}

    /**
     * Rephrase text using AI.
     */
    public function rephrase(Request $request, string $taskId): JsonResponse
    {
        $request->validate([
            'text' => 'required|string|max:5000',
        ]);

        $this->findTask($request, $taskId);
        $org = $request->user()->organization;

        if (! $this->aiService->canMakeRequest($org)) {
            return response()->json(['message' => 'Quota IA atteint. Passez à un plan supérieur.'], 429);
        }

        $result = $this->aiService->rephraseText(
            $request->input('text'),
            $org,
            $request->user()->id,
        );

        return response()->json([
            'text' => $result->text,
            'usage' => [
                'provider' => $result->provider,
                'used_fallback' => $result->usedFallback,
                'prompt_tokens' => $result->promptTokens,
                'completion_tokens' => $result->completionTokens,
            ],
        ]);
    }

    /**
     * Describe a photo using AI.
     */
    public function describePhoto(Request $request, string $taskId): JsonResponse
    {
        $request->validate([
            'photo' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $task = $this->findTask($request, $taskId);
        $org = $request->user()->organization;

        if (! $this->aiService->canMakeRequest($org)) {
            return response()->json(['message' => 'Quota IA atteint. Passez à un plan supérieur.'], 429);
        }

        // Upload photo to storage
        $media = $this->storageService->upload(
            $org,
            $request->file('photo'),
            'photos',
            $task,
            $request->user()->id,
        );

        $result = $this->aiService->describePhoto(
            $media->file_path,
            $org,
            $request->user()->id,
            $media->disk,
        );

        return response()->json([
            'description' => $result->text,
            'media_id' => $media->id,
            'usage' => [
                'provider' => $result->provider,
                'used_fallback' => $result->usedFallback,
                'prompt_tokens' => $result->promptTokens,
                'completion_tokens' => $result->completionTokens,
            ],
        ]);
    }

    /**
     * Transcribe audio using AI.
     */
    public function transcribe(Request $request, string $taskId): JsonResponse
    {
        $request->validate([
            'audio' => 'required|file|max:' . (config('media.max_upload_size_mb', 50) * 1024),
        ]);

        // Validate extension manually (Laravel mimes rule uses finfo which detects .m4a as video/mp4)
        $allowedExts = config('media.audio_mimes', ['mp3', 'wav', 'm4a', 'ogg', 'webm']);
        $ext = strtolower($request->file('audio')->getClientOriginalExtension());
        if (! in_array($ext, $allowedExts)) {
            abort(422, "Type de fichier audio non autorisé. Extensions acceptées : " . implode(', ', $allowedExts));
        }

        $task = $this->findTask($request, $taskId);
        $org = $request->user()->organization;

        if (! $this->aiService->canMakeRequest($org)) {
            return response()->json(['message' => 'Quota IA atteint. Passez à un plan supérieur.'], 429);
        }

        // Upload audio to storage
        $media = $this->storageService->upload(
            $org,
            $request->file('audio'),
            'audio',
            $task,
            $request->user()->id,
        );

        $result = $this->aiService->transcribeAudio(
            $media->file_path,
            $org,
            $request->user()->id,
            $media->disk,
            $media->mime_type,
        );

        return response()->json([
            'transcription' => $result->text,
            'media_id' => $media->id,
            'usage' => [
                'provider' => $result->provider,
                'used_fallback' => $result->usedFallback,
                'prompt_tokens' => $result->promptTokens,
                'completion_tokens' => $result->completionTokens,
            ],
        ]);
    }

    /**
     * Describe a video using AI.
     */
    public function describeVideo(Request $request, string $taskId): JsonResponse
    {
        $request->validate([
            'video' => 'required|file|max:' . (config('media.max_upload_size_mb', 50) * 1024),
        ]);

        // Validate extension manually (Laravel mimes rule can misdetect container formats)
        $allowedExts = config('media.video_mimes', ['mp4', 'mov', 'webm']);
        $ext = strtolower($request->file('video')->getClientOriginalExtension());
        if (! in_array($ext, $allowedExts)) {
            abort(422, "Type de fichier vidéo non autorisé. Extensions acceptées : " . implode(', ', $allowedExts));
        }

        $task = $this->findTask($request, $taskId);
        $org = $request->user()->organization;

        if (! $this->aiService->canMakeRequest($org)) {
            return response()->json(['message' => 'Quota IA atteint. Passez à un plan supérieur.'], 429);
        }

        // Upload video to storage
        $media = $this->storageService->upload(
            $org,
            $request->file('video'),
            'video',
            $task,
            $request->user()->id,
        );

        $result = $this->aiService->describeVideo(
            $media->file_path,
            $org,
            $request->user()->id,
            $media->disk,
            $media->mime_type,
        );

        return response()->json([
            'description' => $result->text,
            'media_id' => $media->id,
            'usage' => [
                'provider' => $result->provider,
                'used_fallback' => $result->usedFallback,
                'prompt_tokens' => $result->promptTokens,
                'completion_tokens' => $result->completionTokens,
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
