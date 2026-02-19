<?php

namespace App\Filament\Widgets\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class UsersByRoleChart extends ChartWidget
{
    protected static ?string $heading = 'Utilisateurs par rôle';

    protected static ?int $sort = 4;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $colorMap = [
            'danger' => '#ef4444',
            'warning' => '#f59e0b',
            'info' => '#3b82f6',
            'success' => '#10b981',
            'gray' => '#6b7280',
        ];

        $labels = [];
        $data = [];
        $colors = [];

        foreach (UserRole::cases() as $role) {
            $count = User::where('role', $role->value)->count();
            if ($count > 0) {
                $labels[] = $role->getLabel();
                $data[] = $count;
                $colors[] = $colorMap[$role->getColor()] ?? '#6b7280';
            }
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
        ];
    }
}
