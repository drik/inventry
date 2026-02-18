<?php

namespace App\Filament\App\Resources\AssetResource\Actions;

use App\Enums\AssetStatus;
use App\Models\Assignment;
use App\Models\AssetStatusHistory;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CheckOutAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'check_out';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Check Out')
            ->icon('heroicon-o-arrow-right-circle')
            ->color('success')
            ->visible(fn (Model $record) => $record->status === AssetStatus::Available)
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
            ->action(function (Model $record, array $data): void {
                $previousStatus = $record->status;

                // Create assignment
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

                // Update asset status
                $record->update(['status' => AssetStatus::Assigned]);

                // Log status history
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
            ->successNotificationTitle('Asset checked out successfully');
    }
}
