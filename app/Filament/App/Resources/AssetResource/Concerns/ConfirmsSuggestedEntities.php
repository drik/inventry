<?php

namespace App\Filament\App\Resources\AssetResource\Concerns;

use App\Models\Asset;

trait ConfirmsSuggestedEntities
{
    protected function confirmSuggestedEntities(Asset $asset): void
    {
        foreach (['category', 'manufacturer', 'assetModel', 'location', 'supplier'] as $relation) {
            $entity = $asset->$relation;
            if ($entity && $entity->suggested === true) {
                $entity->update(['suggested' => false]);
            }
        }
    }
}
