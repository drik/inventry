<?php

namespace App\Filament\Widgets\Admin;

use App\Models\AiRecognitionLog;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AiUsageTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Requêtes IA — 30 derniers jours';

    protected static ?int $sort = 10;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = collect();
        $geminiData = collect();
        $openaiData = collect();

        // Get daily counts per provider for the last 30 days
        $results = AiRecognitionLog::query()
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->select(
                DB::raw('DATE(created_at) as day'),
                'provider',
                DB::raw('COUNT(*) as total'),
            )
            ->groupBy('day', 'provider')
            ->get()
            ->groupBy('day');

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateKey = $date->format('Y-m-d');
            $days->push($date->format('d/m'));

            $dayData = $results->get($dateKey, collect());

            $geminiData->push(
                $dayData->firstWhere('provider', 'gemini')?->total ?? 0
            );
            $openaiData->push(
                $dayData->firstWhere('provider', 'openai')?->total ?? 0
            );
        }

        return [
            'datasets' => [
                [
                    'label' => 'Gemini Flash',
                    'data' => $geminiData->toArray(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'GPT-4o',
                    'data' => $openaiData->toArray(),
                    'borderColor' => '#8b5cf6',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $days->toArray(),
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
