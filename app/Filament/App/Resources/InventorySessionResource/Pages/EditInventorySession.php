<?php

namespace App\Filament\App\Resources\InventorySessionResource\Pages;

use App\Filament\App\Resources\InventorySessionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInventorySession extends EditRecord
{
    protected static string $resource = InventorySessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
