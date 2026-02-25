<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryNote;
use App\Models\InventoryTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    /**
     * Add a note to an inventory item.
     */
    public function storeForItem(Request $request, string $taskId, string $itemId): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:5000',
            'source_type' => 'nullable|in:text,ai_rephrase,ai_photo_desc,ai_audio_transcript,ai_video_desc',
            'source_media_id' => 'nullable|string',
            'original_content' => 'nullable|string|max:5000',
        ]);

        $task = $this->findTask($request, $taskId);
        $item = InventoryItem::withoutGlobalScopes()
            ->where('organization_id', $request->user()->organization_id)
            ->where('session_id', $task->session_id)
            ->where(fn ($q) => $q->where('id', $itemId)->orWhere('asset_id', $itemId))
            ->firstOrFail();

        $note = InventoryNote::create([
            'organization_id' => $request->user()->organization_id,
            'notable_type' => InventoryItem::class,
            'notable_id' => $item->id,
            'content' => $request->input('content'),
            'original_content' => $request->input('original_content'),
            'source_type' => $request->input('source_type', 'text'),
            'source_media_id' => $request->input('source_media_id'),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'note' => $this->formatNote($note),
        ], 201);
    }

    /**
     * Add a note to a task.
     */
    public function storeForTask(Request $request, string $taskId): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:5000',
            'source_type' => 'nullable|in:text,ai_rephrase,ai_photo_desc,ai_audio_transcript,ai_video_desc',
            'source_media_id' => 'nullable|string',
            'original_content' => 'nullable|string|max:5000',
        ]);

        $task = $this->findTask($request, $taskId);

        $note = InventoryNote::create([
            'organization_id' => $request->user()->organization_id,
            'notable_type' => InventoryTask::class,
            'notable_id' => $task->id,
            'content' => $request->input('content'),
            'original_content' => $request->input('original_content'),
            'source_type' => $request->input('source_type', 'text'),
            'source_media_id' => $request->input('source_media_id'),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'note' => $this->formatNote($note),
        ], 201);
    }

    /**
     * List notes for an inventory item.
     */
    public function indexForItem(Request $request, string $taskId, string $itemId): JsonResponse
    {
        $task = $this->findTask($request, $taskId);
        $item = InventoryItem::withoutGlobalScopes()
            ->where('organization_id', $request->user()->organization_id)
            ->where('session_id', $task->session_id)
            ->where(fn ($q) => $q->where('id', $itemId)->orWhere('asset_id', $itemId))
            ->firstOrFail();

        $notes = $item->notes()->with('creator')->orderByDesc('created_at')->get();

        return response()->json([
            'notes' => $notes->map(fn ($note) => $this->formatNote($note)),
        ]);
    }

    /**
     * List notes for a task.
     */
    public function indexForTask(Request $request, string $taskId): JsonResponse
    {
        $task = $this->findTask($request, $taskId);

        $notes = $task->notes()->with('creator')->orderByDesc('created_at')->get();

        return response()->json([
            'notes' => $notes->map(fn ($note) => $this->formatNote($note)),
        ]);
    }

    /**
     * Delete a note.
     */
    public function destroy(Request $request, string $noteId): JsonResponse
    {
        $note = InventoryNote::where('organization_id', $request->user()->organization_id)
            ->findOrFail($noteId);

        $note->delete();

        return response()->json(['message' => 'Note supprimée.']);
    }

    protected function formatNote(InventoryNote $note): array
    {
        return [
            'id' => $note->id,
            'content' => $note->content,
            'original_content' => $note->original_content,
            'source_type' => $note->source_type,
            'source_media_id' => $note->source_media_id,
            'created_by' => $note->created_by,
            'creator_name' => $note->creator?->name,
            'created_at' => $note->created_at->toIso8601String(),
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
