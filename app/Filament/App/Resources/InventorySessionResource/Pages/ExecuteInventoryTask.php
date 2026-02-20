<?php

namespace App\Filament\App\Resources\InventorySessionResource\Pages;

use App\Enums\InventoryItemStatus;
use App\Enums\InventorySessionStatus;
use App\Filament\App\Resources\InventorySessionResource;
use App\Models\Asset;
use App\Models\InventorySession;
use App\Models\InventoryTask;
use App\Services\InventoryScanService;
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

    protected function scanService(): InventoryScanService
    {
        return app(InventoryScanService::class);
    }

    public function scanBarcode(): void
    {
        $code = trim($this->barcode);
        $this->barcode = '';

        if (empty($code)) {
            return;
        }

        $result = $this->scanService()->scanBarcode($code, $this->record, $this->task, Auth::id());

        if (! $result['found']) {
            $this->scanFeedback = $result['message'];
            $this->scanFeedbackType = 'danger';
            $this->lastScannedAsset = null;
            $this->dispatch('barcode-processed');

            return;
        }

        $asset = $result['asset'];

        if (! $result['is_unexpected']) {
            if ($result['already_scanned']) {
                $this->scanFeedback = "{$asset->asset_code} — {$asset->name} already marked as found.";
                $this->scanFeedbackType = 'warning';
            } else {
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

        $item = $this->scanService()->addUnexpected($asset, $this->record, $this->task, Auth::id());

        if ($item) {
            $this->refreshCounters();
            $this->scanFeedback = "{$asset->asset_code} added as unexpected item.";
            $this->scanFeedbackType = 'success';
            $this->lastScannedAsset['is_unexpected'] = false;
        }
    }

    public function markItemFound(string $itemId): void
    {
        $item = $this->record->items()->findOrFail($itemId);
        $this->scanService()->markItemFound($item, $this->task, Auth::id());
        $this->refreshCounters();
    }

    public function markItemMissing(string $itemId): void
    {
        $item = $this->record->items()->findOrFail($itemId);
        $this->scanService()->markItemMissing($item);
        $this->refreshCounters();
    }

    public function completeTask(): void
    {
        $this->scanService()->completeTask($this->task, Auth::id());

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
    }
}
