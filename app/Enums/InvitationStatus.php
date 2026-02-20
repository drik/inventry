<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum InvitationStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted => 'success',
            self::Cancelled => 'gray',
            self::Expired => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Accepted => 'heroicon-o-check-circle',
            self::Cancelled => 'heroicon-o-x-circle',
            self::Expired => 'heroicon-o-exclamation-triangle',
        };
    }
}
