<?php

namespace App\Filament\App\Resources\LocationResource\Pages;

use App\Enums\PlanFeature;
use App\Filament\App\Resources\LocationResource;
use App\Filament\Concerns\ChecksPlanLimits;
use Filament\Resources\Pages\CreateRecord;

class CreateLocation extends CreateRecord
{
    use ChecksPlanLimits;

    protected static string $resource = LocationResource::class;

    protected static function getPlanFeature(): ?PlanFeature
    {
        return PlanFeature::MaxLocations;
    }
}
