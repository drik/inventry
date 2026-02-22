<?php

namespace App\Filament\App\Resources\AssetResource\Pages;

use App\Enums\PlanFeature;
use App\Filament\App\Resources\AssetResource;
use App\Filament\Concerns\ChecksPlanLimits;
use Filament\Resources\Pages\CreateRecord;

class CreateAsset extends CreateRecord
{
    use ChecksPlanLimits;

    protected static string $resource = AssetResource::class;

    protected static function getPlanFeature(): ?PlanFeature
    {
        return PlanFeature::MaxAssets;
    }
}
