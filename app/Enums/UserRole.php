<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;

enum UserRole: string implements HasLabel, HasColor, HasIcon
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Manager = 'manager';
    case Technician = 'technician';
    case User = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Technician => 'Technician',
            self::User => 'User',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::Admin => 'warning',
            self::Manager => 'info',
            self::Technician => 'success',
            self::User => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::SuperAdmin => 'heroicon-o-shield-check',
            self::Admin => 'heroicon-o-cog-6-tooth',
            self::Manager => 'heroicon-o-briefcase',
            self::Technician => 'heroicon-o-wrench',
            self::User => 'heroicon-o-user',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::SuperAdmin => 5,
            self::Admin => 4,
            self::Manager => 3,
            self::Technician => 2,
            self::User => 1,
        };
    }

    /**
     * Get roles that the given role can assign (same level or below, excluding SuperAdmin).
     */
    public static function assignableBy(self $role): array
    {
        return collect(self::cases())
            ->filter(fn (self $case) => $case !== self::SuperAdmin && $case->level() <= $role->level())
            ->values()
            ->all();
    }
}
