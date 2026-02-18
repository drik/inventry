<?php

namespace App\Filament\App\Resources\AssetResource\Actions;

use App\Enums\AssetStatus;
use App\Models\AssetStatusHistory;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CheckInAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'check_in';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Check In')
            ->icon('heroicon-o-arrow-left-circle')
            ->color('warning')
            ->visible(fn (Model $record) => $record->status === AssetStatus::Assigned)
            ->form([
                Forms\Components\Textarea::make('return_condition')
                    ->label('Return Condition'),

                Forms\Components\Textarea::make('notes'),
            ])
            ->action(function (Model $record, array $data): void {
                $previousStatus = $record->status;

                // Find the active assignment and close it
                $activeAssignment = $record->currentAssignment;

                if ($activeAssignment) {
                    $activeAssignment->update([
                        'returned_at' => now(),
                        'return_condition' => $data['return_condition'] ?? null,
                        'return_accepted_by' => Auth::id(),
                        'notes' => $data['notes'] ?? $activeAssignment->notes,
                    ]);
                }

                // Update asset status
                $record->update(['status' => AssetStatus::Available]);

                // Log status history
                AssetStatusHistory::create([
                    'organization_id' => Filament::getTenant()->id,
                    'asset_id' => $record->id,
                    'from_status' => $previousStatus,
                    'to_status' => AssetStatus::Available,
                    'changed_by' => Auth::id(),
                    'reason' => 'Checked in' . ($data['return_condition'] ? ': ' . $data['return_condition'] : ''),
                    'created_at' => now(),
                ]);
            })
            ->successNotificationTitle('Asset checked in successfully');
    }
}
