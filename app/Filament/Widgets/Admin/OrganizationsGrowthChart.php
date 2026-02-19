<?php

namespace App\Filament\Widgets\Admin;

use App\Models\Organization;
use Filament\Widgets\ChartWidget;

class OrganizationsGrowthChart extends ChartWidget
{
    protected static ?string $heading = 'Croissance des organisations';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $months = collect();
        $newOrgs = collect();
        $cumulative = collect();

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months->push($date->translatedFormat('M Y'));

            $newOrgs->push(
                Organization::whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->count()
            );

            $cumulative->push(
                Organization::where('created_at', '<=', $date->endOfMonth())->count()
            );
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total cumulé',
                    'data' => $cumulative->toArray(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Nouvelles/mois',
                    'data' => $newOrgs->toArray(),
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
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
