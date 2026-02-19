<?php

namespace App\Filament\App\Resources\InventorySessionResource\Pages;

use App\Enums\InventoryItemStatus;
use App\Enums\InventorySessionStatus;
use App\Filament\App\Resources\InventorySessionResource;
use App\Models\Asset;
use App\Notifications\InventoryTaskAssigned;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ViewRecord;

class ViewInventorySession extends ViewRecord
{
    protected static string $resource = InventorySessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('execute_scan')
                ->label('Execute Scan')
                ->icon('heroicon-o-qr-code')
                ->color('primary')
                ->visible(fn () => $this->record->status === InventorySessionStatus::InProgress)
                ->url(fn () => InventorySessionResource::getUrl('execute', ['record' => $this->record])),

            Actions\Action::make('start_session')
                ->label('Start Session')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->record->status === InventorySessionStatus::Draft)
                ->requiresConfirmation()
                ->action(function (): void {
                    $session = $this->record;

                    // Build the asset query based on scope
                    $query = Asset::withoutGlobalScopes()
                        ->where('organization_id', Filament::getTenant()->id);

                    if ($session->scope_type === 'location' && ! empty($session->scope_ids)) {
                        $query->whereIn('location_id', $session->scope_ids);
                    } elseif ($session->scope_type === 'category' && ! empty($session->scope_ids)) {
                        $query->whereIn('category_id', $session->scope_ids);
                    } elseif ($session->scope_type === 'department' && ! empty($session->scope_ids)) {
                        $query->whereIn('department_id', $session->scope_ids);
                    }

                    $assets = $query->get();

                    // Create inventory items for each asset
                    foreach ($assets as $asset) {
                        $session->items()->create([
                            'organization_id' => Filament::getTenant()->id,
                            'asset_id' => $asset->id,
                            'status' => InventoryItemStatus::Expected,
                        ]);
                    }

                    // Update session
                    $session->update([
                        'status' => InventorySessionStatus::InProgress,
                        'started_at' => now(),
                        'total_expected' => $assets->count(),
                    ]);

                    // Notify assigned users (except session creator)
                    $session->load('tasks.assignee', 'tasks.location');
                    $session->tasks->each(function ($task) use ($session) {
                        if ($task->assigned_to !== $session->created_by && $task->assignee) {
                            $task->assignee->notify(new InventoryTaskAssigned($task));
                        }
                    });
                })
                ->successNotificationTitle('Session started'),

            Actions\Action::make('complete_session')
                ->label('Complete Session')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $this->record->status === InventorySessionStatus::InProgress)
                ->requiresConfirmation()
                ->action(function (): void {
                    $session = $this->record;

                    // Mark unscanned items as missing
                    $session->items()
                        ->where('status', InventoryItemStatus::Expected)
                        ->update(['status' => InventoryItemStatus::Missing]);

                    // Compute stats
                    $session->update([
                        'status' => InventorySessionStatus::Completed,
                        'completed_at' => now(),
                        'total_scanned' => $session->items()->whereNotNull('scanned_at')->count(),
                        'total_matched' => $session->items()->where('status', InventoryItemStatus::Found)->count(),
                        'total_missing' => $session->items()->where('status', InventoryItemStatus::Missing)->count(),
                        'total_unexpected' => $session->items()->where('status', InventoryItemStatus::Unexpected)->count(),
                    ]);
                })
                ->successNotificationTitle('Session completed'),

            Actions\Action::make('cancel_session')
                ->label('Cancel')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn () => in_array($this->record->status, [
                    InventorySessionStatus::Draft,
                    InventorySessionStatus::InProgress,
                ]))
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'status' => InventorySessionStatus::Cancelled,
                    ]);
                })
                ->successNotificationTitle('Session cancelled'),

            Actions\EditAction::make()
                ->visible(fn () => $this->record->status === InventorySessionStatus::Draft),
        ];
    }
}
