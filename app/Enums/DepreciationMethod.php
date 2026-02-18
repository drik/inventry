<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DepreciationMethod: string implements HasLabel
{
    case StraightLine = 'straight_line';
    case DecliningBalance = 'declining_balance';
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::StraightLine => 'Straight Line',
            self::DecliningBalance => 'Declining Balance',
            self::None => 'No Depreciation',
        };
    }
}
