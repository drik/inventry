<?php

namespace App\Filament\App\Resources\AssetModelResource\Pages;

use App\Filament\App\Resources\AssetModelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAssetModel extends EditRecord
{
    protected static string $resource = AssetModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
