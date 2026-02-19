<?php

namespace App\Filament\App\Resources\InventorySessionResource\Pages;

use App\Enums\InventoryItemStatus;
use App\Enums\InventorySessionStatus;
use App\Filament\App\Resources\InventorySessionResource;
use App\Models\Asset;
use App\Models\InventoryItem;
use App\Models\InventorySession;
use App\Models\InventoryTask;
use App\Notifications\InventoryTaskCompleted;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class ExecuteInventoryTask extends Page
{
    protected static string $resource = InventorySessionResource::class;

    protected static string $view = 'filament.app.resources.inventory-session-resource.pages.execute-inventory-task';

    protected static ?string $title = 'Execute Task';

    public $record; // InventorySession

    public InventoryTask $task;

    public string $barcode = '';

    public ?string $scanFeedback = null;

    public ?string $scanFeedbackType = null;

    public ?array $lastScannedAsset = null;

    public string $activeTab = 'all';

    public function mount(int|string $record, string $taskId): void
    {
        $this->record = InventorySession::findOrFail($record);
        $this->task = InventoryTask::findOrFail($taskId);

        abort_unless(
            $this->record->status === InventorySessionStatus::InProgress,
            403,
            'Session must be in progress.',
        );

        abort_unless(
            $this->task->session_id === $this->record->id,
            404,
        );

        abort_unless(
            $this->task->assigned_to === Auth::id(),
            403,
            'This task is not assigned to you.',
        );

        // Auto-start the task if pending
        if ($this->task->status === 'pending') {
            $this->task->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
        }
    }

    public function getTitle(): string
    {
        $title = $this->record->name;
        if ($this->task->location) {
            $title .= ' — ' . $this->task->location->name;
        }

        return $title;
    }

    /**
     * Get session items scoped to this task's location.
     */
    protected function scopedItemsQuery()
    {
        $query = $this->record->items()->with(['asset.location', 'scanner']);

        if ($this->task->location_id) {
            $query->whereHas('asset', fn ($q) => $q->where('location_id', $this->task->location_id));
        }

        return $query;
    }

    public function scanBarcode(): void
    {
        $code = trim($this->barcode);
        $this->barcode = '';

        if (empty($code)) {
            return;
        }

        $asset = Asset::where('barcode', $code)
            ->orWhere('asset_code', $code)
            ->orWhereHas('tagValues', fn ($q) => $q->where('value', $code))
            ->first();

        if (! $asset) {
            $this->scanFeedback = "No asset found with code: {$code}";
            $this->scanFeedbackType = 'danger';
            $this->lastScannedAsset = null;
            $this->dispatch('barcode-processed');

            return;
        }

        // Find matching InventoryItem in this session
        $item = $this->record->items()
            ->where('asset_id', $asset->id)
            ->first();

        if ($item) {
            if ($item->status === InventoryItemStatus::Found) {
                $this->scanFeedback = "{$asset->asset_code} — {$asset->name} already marked as found.";
                $this->scanFeedbackType = 'warning';
            } else {
                $item->update([
                    'status' => InventoryItemStatus::Found,
                    'scanned_at' => now(),
                    'scanned_by' => Auth::id(),
                    'task_id' => $this->task->id,
                ]);
                $this->refreshCounters();
                $this->scanFeedback = "{$asset->asset_code} — {$asset->name} marked as Found!";
                $this->scanFeedbackType = 'success';
            }

            $this->lastScannedAsset = [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'location' => $asset->location?->name,
                'category' => $asset->category?->name,
                'is_unexpected' => false,
            ];
        } else {
            $this->scanFeedback = "{$asset->asset_code} — {$asset->name} is not on the expected list.";
            $this->scanFeedbackType = 'warning';
            $this->lastScannedAsset = [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'location' => $asset->location?->name,
                'category' => $asset->category?->name,
                'is_unexpected' => true,
            ];
        }

        $this->dispatch('barcode-processed');
    }

    public function addUnexpected(?string $assetId = null): void
    {
        $targetId = $assetId ?? ($this->lastScannedAsset['id'] ?? null);

        if (! $targetId) {
            return;
        }

        $asset = Asset::find($targetId);

        if (! $asset) {
            return;
        }

        $exists = $this->record->items()
            ->where('asset_id', $asset->id)
            ->exists();

        if (! $exists) {
            $this->record->items()->create([
                'organization_id' => Filament::getTenant()->id,
                'asset_id' => $asset->id,
                'task_id' => $this->task->id,
                'status' => InventoryItemStatus::Unexpected,
                'scanned_at' => now(),
                'scanned_by' => Auth::id(),
            ]);
            $this->refreshCounters();
            $this->scanFeedback = "{$asset->asset_code} added as unexpected item.";
            $this->scanFeedbackType = 'success';
            $this->lastScannedAsset['is_unexpected'] = false;
        }
    }

    public function markItemFound(string $itemId): void
    {
        $item = $this->record->items()->findOrFail($itemId);
        $item->update([
            'status' => InventoryItemStatus::Found,
            'scanned_at' => now(),
            'scanned_by' => Auth::id(),
            'task_id' => $this->task->id,
        ]);
        $this->refreshCounters();
    }

    public function markItemMissing(string $itemId): void
    {
        $item = $this->record->items()->findOrFail($itemId);
        $item->update(['status' => InventoryItemStatus::Missing]);
        $this->refreshCounters();
    }

    public function completeTask(): void
    {
        $this->task->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Notify session creator
        $creator = $this->task->session->creator;
        if ($creator && $creator->id !== Auth::id()) {
            $creator->notify(new InventoryTaskCompleted($this->task));
        }

        // Also refresh session counters
        $this->refreshSessionCounters();

        $this->redirect(route('filament.app.pages.my-inventory-tasks', [
            'tenant' => Filament::getTenant(),
        ]));
    }

    #[Computed]
    public function items()
    {
        $query = $this->scopedItemsQuery();

        if ($this->activeTab !== 'all') {
            $query->where('status', $this->activeTab);
        }

        return $query->orderByDesc('scanned_at')->get();
    }

    #[Computed]
    public function stats(): array
    {
        $base = $this->scopedItemsQuery();

        $total = (clone $base)->count();
        $scanned = (clone $base)->whereNotNull('scanned_at')->count();
        $found = (clone $base)->where('status', InventoryItemStatus::Found)->count();
        $missing = (clone $base)->where('status', InventoryItemStatus::Missing)->count();
        $unexpected = (clone $base)->where('status', InventoryItemStatus::Unexpected)->count();

        return [
            'expected' => $total,
            'scanned' => $scanned,
            'found' => $found,
            'missing' => $missing,
            'unexpected' => $unexpected,
            'progress' => $total > 0 ? round(($scanned / $total) * 100) : 0,
        ];
    }

    protected function refreshCounters(): void
    {
        unset($this->items, $this->stats);
        $this->refreshSessionCounters();
    }

    protected function refreshSessionCounters(): void
    {
        $session = $this->record;
        $session->update([
            'total_scanned' => $session->items()->whereNotNull('scanned_at')->count(),
            'total_matched' => $session->items()->where('status', InventoryItemStatus::Found)->count(),
            'total_missing' => $session->items()->where('status', InventoryItemStatus::Missing)->count(),
            'total_unexpected' => $session->items()->where('status', InventoryItemStatus::Unexpected)->count(),
        ]);
        $session->refresh();
    }
}
