<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasLabel, HasColor, HasIcon
{
    case Active = 'active';
    case Trialing = 'trialing';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Paused = 'paused';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Trialing => 'Essai',
            self::PastDue => 'En retard',
            self::Cancelled => 'Annulé',
            self::Paused => 'En pause',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::Trialing => 'info',
            self::PastDue => 'warning',
            self::Cancelled => 'danger',
            self::Paused => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::Trialing => 'heroicon-o-clock',
            self::PastDue => 'heroicon-o-exclamation-triangle',
            self::Cancelled => 'heroicon-o-x-circle',
            self::Paused => 'heroicon-o-pause-circle',
        };
    }
}
