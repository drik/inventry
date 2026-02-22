<?php

namespace App\Filament\App\Resources\InventorySessionResource\Pages;

use App\Enums\PlanFeature;
use App\Filament\App\Resources\InventorySessionResource;
use App\Filament\Concerns\ChecksPlanLimits;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateInventorySession extends CreateRecord
{
    use ChecksPlanLimits;

    protected static string $resource = InventorySessionResource::class;

    protected static function getPlanFeature(): ?PlanFeature
    {
        return PlanFeature::MaxActiveInventorySessions;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }
}
