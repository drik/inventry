<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum InventoryItemStatus: string implements HasLabel, HasColor, HasIcon
{
    case Expected = 'expected';
    case Found = 'found';
    case Missing = 'missing';
    case Unexpected = 'unexpected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Expected => 'Expected',
            self::Found => 'Found',
            self::Missing => 'Missing',
            self::Unexpected => 'Unexpected',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Expected => 'gray',
            self::Found => 'success',
            self::Missing => 'danger',
            self::Unexpected => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Expected => 'heroicon-o-clock',
            self::Found => 'heroicon-o-check',
            self::Missing => 'heroicon-o-x-mark',
            self::Unexpected => 'heroicon-o-question-mark-circle',
        };
    }
}
