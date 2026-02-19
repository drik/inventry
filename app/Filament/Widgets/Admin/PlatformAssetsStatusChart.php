<?php

namespace App\Filament\Widgets\Admin;

use App\Enums\AssetStatus;
use App\Models\Asset;
use Filament\Widgets\ChartWidget;

class PlatformAssetsStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Assets par statut (plateforme)';

    protected static ?int $sort = 5;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $colorMap = [
            'success' => '#10b981',
            'info' => '#3b82f6',
            'warning' => '#f59e0b',
            'gray' => '#6b7280',
            'danger' => '#ef4444',
        ];

        $labels = [];
        $data = [];
        $colors = [];

        foreach (AssetStatus::cases() as $status) {
            $count = Asset::where('status', $status->value)->count();
            if ($count > 0) {
                $labels[] = $status->getLabel();
                $data[] = $count;
                $colors[] = $colorMap[$status->getColor()] ?? '#6b7280';
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
