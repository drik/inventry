<?php

namespace App\Filament\App\Resources\InventorySessionResource\Pages;

use App\Enums\InventoryItemStatus;
use App\Enums\InventorySessionStatus;
use App\Filament\App\Resources\InventorySessionResource;
use App\Models\Asset;
use App\Models\InventorySession;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class ExecuteInventorySession extends Page
{
    protected static string $resource = InventorySessionResource::class;

    protected static string $view = 'filament.app.resources.inventory-session-resource.pages.execute-inventory-session';

    protected static ?string $title = 'Execute Inventory';

    public $record;

    public string $barcode = '';

    public ?string $scanFeedback = null;

    public ?string $scanFeedbackType = null;

    public ?array $lastScannedAsset = null;

    public string $activeTab = 'all';

    public function mount(int|string $record): void
    {
        $this->record = InventorySession::findOrFail($record);

        abort_unless(
            $this->record->status === InventorySessionStatus::InProgress,
            403,
            'Session must be in progress to execute.',
        );
    }

    public function getTitle(): string
    {
        return 'Execute: ' . $this->record->name;
    }

    public function scanBarcode(): void
    {
        $code = trim($this->barcode);
        $this->barcode = '';

        if (empty($code)) {
            return;
        }

        // Find asset by barcode, asset_code, or tag value
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
                ]);
                $this->refreshSessionCounters();
                $this->scanFeedback = "{$asset->asset_code} — {$asset->name} marked as Found!";
                $this->scanFeedbackType = 'success';
            }

            $this->lastScannedAsset = [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'location' => $asset->location?->name,
                'category' => $asset->category?->name,
                'status' => $asset->status->getLabel(),
                'is_unexpected' => false,
            ];
        } else {
            // Asset not on expected list
            $this->scanFeedback = "{$asset->asset_code} — {$asset->name} is not on the expected list.";
            $this->scanFeedbackType = 'warning';
            $this->lastScannedAsset = [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'location' => $asset->location?->name,
                'category' => $asset->category?->name,
                'status' => $asset->status->getLabel(),
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
                'status' => InventoryItemStatus::Unexpected,
                'scanned_at' => now(),
                'scanned_by' => Auth::id(),
            ]);
            $this->refreshSessionCounters();
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
        ]);
        $this->refreshSessionCounters();
    }

    public function markItemMissing(string $itemId): void
    {
        $item = $this->record->items()->findOrFail($itemId);
        $item->update(['status' => InventoryItemStatus::Missing]);
        $this->refreshSessionCounters();
    }

    public function completeSession(): void
    {
        $session = $this->record;

        $session->items()
            ->where('status', InventoryItemStatus::Expected)
            ->update(['status' => InventoryItemStatus::Missing]);

        $session->update([
            'status' => InventorySessionStatus::Completed,
            'completed_at' => now(),
            'total_scanned' => $session->items()->whereNotNull('scanned_at')->count(),
            'total_matched' => $session->items()->where('status', InventoryItemStatus::Found)->count(),
            'total_missing' => $session->items()->where('status', InventoryItemStatus::Missing)->count(),
            'total_unexpected' => $session->items()->where('status', InventoryItemStatus::Unexpected)->count(),
        ]);

        $this->redirect(InventorySessionResource::getUrl('view', [
            'record' => $this->record,
        ]));
    }

    #[Computed]
    public function items()
    {
        $query = $this->record->items()->with(['asset.location', 'scanner']);

        if ($this->activeTab !== 'all') {
            $query->where('status', $this->activeTab);
        }

        return $query->orderByDesc('scanned_at')->get();
    }

    #[Computed]
    public function stats(): array
    {
        $this->record->refresh();

        return [
            'expected' => $this->record->total_expected,
            'scanned' => $this->record->total_scanned,
            'found' => $this->record->total_matched,
            'missing' => $this->record->total_missing,
            'unexpected' => $this->record->total_unexpected,
            'progress' => $this->record->total_expected > 0
                ? round(($this->record->total_scanned / $this->record->total_expected) * 100)
                : 0,
        ];
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
        unset($this->items, $this->stats);
    }
}
