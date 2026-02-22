<?php

namespace App\Services;

use App\Enums\InventoryItemStatus;
use App\Models\Asset;
use App\Models\InventoryItem;
use App\Models\InventorySession;
use App\Models\InventoryTask;
use App\Notifications\InventoryTaskCompleted;

class InventoryScanService
{
    /**
     * Resolve an asset from a barcode/asset_code/tag value.
     */
    public function resolveAsset(string $code, string $organizationId): ?Asset
    {
        return Asset::where('organization_id', $organizationId)
            ->where(function ($query) use ($code) {
                $query->where('barcode', $code)
                    ->orWhere('asset_code', $code)
                    ->orWhereHas('tagValues', fn ($q) => $q->where('value', $code));
            })
            ->first();
    }

    /**
     * Scan a barcode in the context of a session/task.
     *
     * Returns an array with scan result details.
     */
    public function scanBarcode(string $code, InventorySession $session, InventoryTask $task, string $userId): array
    {
        $asset = $this->resolveAsset($code, $session->organization_id);

        if (! $asset) {
            return [
                'found' => false,
                'message' => "Aucun asset trouvé pour ce code : {$code}",
            ];
        }

        // Find matching InventoryItem in this session
        $item = $session->items()->where('asset_id', $asset->id)->first();

        if ($item) {
            if ($item->status === InventoryItemStatus::Found) {
                return [
                    'found' => true,
                    'is_unexpected' => false,
                    'already_scanned' => true,
                    'asset' => $asset,
                    'item' => $item,
                ];
            }

            $item->update([
                'status' => InventoryItemStatus::Found,
                'scanned_at' => now(),
                'scanned_by' => $userId,
                'task_id' => $task->id,
            ]);

            $this->refreshSessionCounters($session);

            return [
                'found' => true,
                'is_unexpected' => false,
                'already_scanned' => false,
                'asset' => $asset,
                'item' => $item->fresh(),
            ];
        }

        // Asset exists but not in session items → unexpected
        return [
            'found' => true,
            'is_unexpected' => true,
            'already_scanned' => false,
            'asset' => $asset,
            'item' => null,
        ];
    }

    /**
     * Add an unexpected asset to the session.
     */
    public function addUnexpected(
        Asset $asset,
        InventorySession $session,
        InventoryTask $task,
        string $userId,
        ?string $conditionNotes = null,
        string $identificationMethod = 'barcode',
        ?string $aiRecognitionLogId = null,
        ?float $aiConfidence = null,
    ): ?InventoryItem {
        $exists = $session->items()->where('asset_id', $asset->id)->exists();

        if ($exists) {
            return null;
        }

        $item = $session->items()->create([
            'organization_id' => $session->organization_id,
            'asset_id' => $asset->id,
            'task_id' => $task->id,
            'status' => InventoryItemStatus::Unexpected,
            'scanned_at' => now(),
            'scanned_by' => $userId,
            'condition_notes' => $conditionNotes,
            'identification_method' => $identificationMethod,
            'ai_recognition_log_id' => $aiRecognitionLogId,
            'ai_confidence' => $aiConfidence,
        ]);

        $this->refreshSessionCounters($session);

        return $item;
    }

    /**
     * Mark an item as found.
     */
    public function markItemFound(
        InventoryItem $item,
        InventoryTask $task,
        string $userId,
        string $identificationMethod = 'barcode',
        ?string $aiRecognitionLogId = null,
        ?float $aiConfidence = null,
    ): void {
        $item->update([
            'status' => InventoryItemStatus::Found,
            'scanned_at' => now(),
            'scanned_by' => $userId,
            'task_id' => $task->id,
            'identification_method' => $identificationMethod,
            'ai_recognition_log_id' => $aiRecognitionLogId,
            'ai_confidence' => $aiConfidence,
        ]);

        $this->refreshSessionCounters($item->session);
    }

    /**
     * Mark an item as missing.
     */
    public function markItemMissing(InventoryItem $item): void
    {
        $item->update(['status' => InventoryItemStatus::Missing]);
        $this->refreshSessionCounters($item->session);
    }

    /**
     * Complete a task: mark unscanned items as missing, notify creator, refresh counters.
     */
    public function completeTask(InventoryTask $task, string $userId): void
    {
        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Notify session creator
        $creator = $task->session->creator;
        if ($creator && $creator->id !== $userId) {
            $creator->notify(new InventoryTaskCompleted($task));
        }

        $this->refreshSessionCounters($task->session);
    }

    /**
     * Refresh the session's aggregate counters.
     */
    public function refreshSessionCounters(InventorySession $session): void
    {
        $session->update([
            'total_scanned' => $session->items()->whereNotNull('scanned_at')->count(),
            'total_matched' => $session->items()->where('status', InventoryItemStatus::Found)->count(),
            'total_missing' => $session->items()->where('status', InventoryItemStatus::Missing)->count(),
            'total_unexpected' => $session->items()->where('status', InventoryItemStatus::Unexpected)->count(),
        ]);
        $session->refresh();
    }
}
