<?php

namespace App\Filament\App\Resources\AssetCategoryResource\Pages;

use App\Filament\App\Resources\AssetCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAssetCategory extends EditRecord
{
    protected static string $resource = AssetCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
