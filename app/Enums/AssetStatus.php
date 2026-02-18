<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AssetStatus: string implements HasLabel, HasColor, HasIcon
{
    case Available = 'available';
    case Assigned = 'assigned';
    case UnderMaintenance = 'under_maintenance';
    case Retired = 'retired';
    case LostStolen = 'lost_stolen';
    case Disposed = 'disposed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Assigned => 'Assigned',
            self::UnderMaintenance => 'Under Maintenance',
            self::Retired => 'Retired',
            self::LostStolen => 'Lost/Stolen',
            self::Disposed => 'Disposed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Available => 'success',
            self::Assigned => 'info',
            self::UnderMaintenance => 'warning',
            self::Retired => 'gray',
            self::LostStolen => 'danger',
            self::Disposed => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Available => 'heroicon-o-check-circle',
            self::Assigned => 'heroicon-o-user',
            self::UnderMaintenance => 'heroicon-o-wrench',
            self::Retired => 'heroicon-o-archive-box',
            self::LostStolen => 'heroicon-o-exclamation-triangle',
            self::Disposed => 'heroicon-o-trash',
        };
    }
}
