<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AssetsByCategoryChart extends ChartWidget
{
    protected static ?string $heading = 'Assets par catégorie';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $palette = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316'];

        $categories = Asset::query()
            ->join('asset_categories', 'assets.category_id', '=', 'asset_categories.id')
            ->select('asset_categories.name', DB::raw('count(*) as total'))
            ->groupBy('asset_categories.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $othersCount = Asset::whereNotIn(
            'category_id',
            $categories->pluck('name')->isEmpty() ? [] : Asset::query()
                ->join('asset_categories', 'assets.category_id', '=', 'asset_categories.id')
                ->select('asset_categories.id')
                ->groupBy('asset_categories.id')
                ->orderByDesc(DB::raw('count(*)'))
                ->limit(8)
                ->pluck('asset_categories.id')
        )->count();

        $labels = $categories->pluck('name')->toArray();
        $data = $categories->pluck('total')->toArray();
        $colors = array_slice($palette, 0, count($labels));

        if ($othersCount > 0) {
            $labels[] = 'Autres';
            $data[] = $othersCount;
            $colors[] = '#6b7280';
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
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
