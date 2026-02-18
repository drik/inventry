<?php

namespace App\Filament\App\Resources\AssignmentResource\Pages;

use App\Enums\AssetStatus;
use App\Filament\App\Resources\AssignmentResource;
use App\Models\Asset;
use App\Models\AssetStatusHistory;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateAssignment extends CreateRecord
{
    protected static string $resource = AssignmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['assigned_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $asset = Asset::find($this->record->asset_id);

        if ($asset && $asset->status !== AssetStatus::Assigned) {
            $previousStatus = $asset->status;

            $asset->update(['status' => AssetStatus::Assigned]);

            AssetStatusHistory::create([
                'organization_id' => Filament::getTenant()->id,
                'asset_id' => $asset->id,
                'from_status' => $previousStatus,
                'to_status' => AssetStatus::Assigned,
                'changed_by' => Auth::id(),
                'reason' => 'Checked out to ' . class_basename($this->record->assignee_type),
                'created_at' => now(),
            ]);
        }
    }
}
