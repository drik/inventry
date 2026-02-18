<?php

namespace App\Filament\App\Resources\AssetCategoryResource\Pages;

use App\Filament\App\Resources\AssetCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssetCategories extends ListRecords
{
    protected static string $resource = AssetCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
