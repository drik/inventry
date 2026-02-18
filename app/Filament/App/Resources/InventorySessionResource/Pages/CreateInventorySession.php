<?php

namespace App\Filament\App\Resources\InventorySessionResource\Pages;

use App\Filament\App\Resources\InventorySessionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateInventorySession extends CreateRecord
{
    protected static string $resource = InventorySessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }
}
