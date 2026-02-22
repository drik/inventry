<?php

namespace App\Filament\App\Resources\UserResource\Pages;

use App\Enums\PlanFeature;
use App\Filament\App\Resources\UserResource;
use App\Filament\Concerns\ChecksPlanLimits;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use ChecksPlanLimits;

    protected static string $resource = UserResource::class;

    protected static function getPlanFeature(): ?PlanFeature
    {
        return PlanFeature::MaxUsers;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = Filament::getTenant()->id;

        return $data;
    }
}
