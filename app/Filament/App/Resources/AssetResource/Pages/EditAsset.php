<?php

namespace App\Filament\App\Resources\AssetResource\Pages;

use App\Filament\App\Resources\AssetResource;
use App\Filament\App\Resources\AssetResource\Concerns\ConfirmsSuggestedEntities;
use App\Filament\App\Resources\AssetResource\Concerns\ValidatesUniqueTagValues;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAsset extends EditRecord
{
    use ConfirmsSuggestedEntities, ValidatesUniqueTagValues;

    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $this->validateUniqueTagValues(excludeAssetId: $this->record->getKey());
    }

    protected function afterSave(): void
    {
        $this->confirmSuggestedEntities($this->record);
    }
}
