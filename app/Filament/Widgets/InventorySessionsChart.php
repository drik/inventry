<?php

namespace App\Filament\Widgets;

use App\Enums\InventorySessionStatus;
use App\Models\InventorySession;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class InventorySessionsChart extends ChartWidget
{
    protected static ?string $heading = 'Sessions d\'inventaire';

    protected static ?int $sort = 5;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $months = collect();
        $totalData = collect();
        $completedData = collect();

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months->push($date->translatedFormat('M Y'));

            $totalData->push(
                InventorySession::whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->count()
            );

            $completedData->push(
                InventorySession::where('status', InventorySessionStatus::Completed)
                    ->whereMonth('completed_at', $date->month)
                    ->whereYear('completed_at', $date->year)
                    ->count()
            );
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total',
                    'data' => $totalData->toArray(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Complétées',
                    'data' => $completedData->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $months->toArray(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
