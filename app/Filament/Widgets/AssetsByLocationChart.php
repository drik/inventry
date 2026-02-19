<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AssetsByLocationChart extends ChartWidget
{
    protected static ?string $heading = 'Assets par emplacement';

    protected static ?int $sort = 4;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $locations = Asset::query()
            ->whereNotNull('location_id')
            ->join('locations', 'assets.location_id', '=', 'locations.id')
            ->select('locations.name', DB::raw('count(*) as total'))
            ->groupBy('locations.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Assets',
                    'data' => $locations->pluck('total')->toArray(),
                    'backgroundColor' => '#3b82f6',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $locations->pluck('name')->toArray(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
