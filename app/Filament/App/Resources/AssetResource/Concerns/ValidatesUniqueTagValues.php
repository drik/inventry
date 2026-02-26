<?php

namespace App\Filament\App\Resources\AssetResource\Concerns;

use App\Models\AssetTag;
use App\Models\AssetTagValue;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

trait ValidatesUniqueTagValues
{
    protected function validateUniqueTagValues(?string $excludeAssetId = null): bool
    {
        $tagValues = $this->data['tagValues'] ?? [];
        $orgId = Filament::getTenant()?->id;

        if (! $orgId || empty($tagValues)) {
            return true;
        }

        foreach ($tagValues as $tagValue) {
            $tagId = $tagValue['asset_tag_id'] ?? null;
            $value = $tagValue['value'] ?? null;

            if (empty($tagId) || empty($value)) {
                continue;
            }

            $tag = AssetTag::find($tagId);

            if (! $tag || ! $tag->is_unique) {
                continue;
            }

            $query = AssetTagValue::where('organization_id', $orgId)
                ->where('asset_tag_id', $tagId)
                ->where('value', $value);

            if ($excludeAssetId) {
                $query->where('asset_id', '!=', $excludeAssetId);
            }

            if ($query->exists()) {
                Notification::make()
                    ->title('Doublon détecté')
                    ->body("La valeur \"{$value}\" existe déjà pour le tag \"{$tag->name}\". Les valeurs de ce tag doivent être uniques.")
                    ->danger()
                    ->send();

                $this->halt();

                return false;
            }
        }

        return true;
    }
}
