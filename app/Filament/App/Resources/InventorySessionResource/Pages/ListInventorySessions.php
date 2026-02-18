<?php

namespace App\Filament\App\Resources\InventorySessionResource\Pages;

use App\Filament\App\Resources\InventorySessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInventorySessions extends ListRecords
{
    protected static string $resource = InventorySessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
