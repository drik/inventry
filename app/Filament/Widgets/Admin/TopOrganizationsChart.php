<?php

namespace App\Filament\Widgets\Admin;

use App\Models\Organization;
use Filament\Widgets\ChartWidget;

class TopOrganizationsChart extends ChartWidget
{
    protected static ?string $heading = 'Top organisations par assets';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $orgs = Organization::withCount('assets')
            ->orderByDesc('assets_count')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Assets',
                    'data' => $orgs->pluck('assets_count')->toArray(),
                    'backgroundColor' => '#3b82f6',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $orgs->pluck('name')->toArray(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
