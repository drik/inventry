<?php

namespace App\Filament\App\Resources\AssetModelResource\Pages;

use App\Filament\App\Resources\AssetModelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssetModels extends ListRecords
{
    protected static string $resource = AssetModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
