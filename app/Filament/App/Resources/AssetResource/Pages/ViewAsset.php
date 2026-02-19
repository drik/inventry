<?php

namespace App\Filament\App\Resources\AssetResource\Pages;

use App\Enums\AssetStatus;
use App\Filament\App\Resources\AssetResource;
use App\Models\Assignment;
use App\Models\AssetStatusHistory;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('check_out')
                ->label('Check Out')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === AssetStatus::Available)
                ->form([
                    Forms\Components\Select::make('assignee_type')
                        ->options([
                            'App\\Models\\User' => 'User',
                            'App\\Models\\Department' => 'Department',
                            'App\\Models\\Location' => 'Location',
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('assignee_id', null)),

                    Forms\Components\Select::make('assignee_id')
                        ->label('Assignee')
                        ->required()
                        ->searchable()
                        ->options(function (Get $get) {
                            return match ($get('assignee_type')) {
                                'App\\Models\\User' => User::pluck('name', 'id'),
                                'App\\Models\\Department' => Department::pluck('name', 'id'),
                                'App\\Models\\Location' => Location::pluck('name', 'id'),
                                default => [],
                            };
                        }),

                    Forms\Components\DatePicker::make('expected_return_at'),

                    Forms\Components\Textarea::make('notes'),
                ])
                ->action(function (array $data): void {
                    $record = $this->record;
                    $previousStatus = $record->status;

                    Assignment::create([
                        'organization_id' => Filament::getTenant()->id,
                        'asset_id' => $record->id,
                        'assignee_type' => $data['assignee_type'],
                        'assignee_id' => $data['assignee_id'],
                        'assigned_by' => Auth::id(),
                        'assigned_at' => now(),
                        'expected_return_at' => $data['expected_return_at'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ]);

                    $record->update(['status' => AssetStatus::Assigned]);

                    AssetStatusHistory::create([
                        'organization_id' => Filament::getTenant()->id,
                        'asset_id' => $record->id,
                        'from_status' => $previousStatus,
                        'to_status' => AssetStatus::Assigned,
                        'changed_by' => Auth::id(),
                        'reason' => 'Checked out to ' . class_basename($data['assignee_type']),
                        'created_at' => now(),
                    ]);
                })
                ->successNotificationTitle('Asset checked out successfully'),

            Actions\Action::make('check_in')
                ->label('Check In')
                ->icon('heroicon-o-arrow-left-circle')
                ->color('warning')
                ->visible(fn () => $this->record->status === AssetStatus::Assigned)
                ->form([
                    Forms\Components\Textarea::make('return_condition')
                        ->label('Return Condition'),

                    Forms\Components\Textarea::make('notes'),
                ])
                ->action(function (array $data): void {
                    $record = $this->record;
                    $previousStatus = $record->status;

                    $activeAssignment = $record->currentAssignment;

                    if ($activeAssignment) {
                        $activeAssignment->update([
                            'returned_at' => now(),
                            'return_condition' => $data['return_condition'] ?? null,
                            'return_accepted_by' => Auth::id(),
                            'notes' => $data['notes'] ?? $activeAssignment->notes,
                        ]);
                    }

                    $record->update(['status' => AssetStatus::Available]);

                    AssetStatusHistory::create([
                        'organization_id' => Filament::getTenant()->id,
                        'asset_id' => $record->id,
                        'from_status' => $previousStatus,
                        'to_status' => AssetStatus::Available,
                        'changed_by' => Auth::id(),
                        'reason' => 'Checked in' . (! empty($data['return_condition']) ? ': ' . $data['return_condition'] : ''),
                        'created_at' => now(),
                    ]);
                })
                ->successNotificationTitle('Asset checked in successfully'),

            Actions\EditAction::make(),
        ];
    }

    public function updateAssetField(string $field, mixed $value): void
    {
        $allowedFields = [
            'name', 'category_id', 'manufacturer_id', 'status',
            'location_id', 'department_id', 'serial_number', 'barcode',
            'purchase_cost', 'purchase_date', 'warranty_expiry',
            'depreciation_method', 'useful_life_months', 'salvage_value',
        ];

        if (! in_array($field, $allowedFields)) {
            return;
        }

        $this->record->update([$field => filled($value) ? $value : null]);
        $this->record->unsetRelations();
        $this->record->refresh();
    }
}
