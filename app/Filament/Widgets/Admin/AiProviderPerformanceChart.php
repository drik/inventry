<?php

namespace App\Filament\Widgets\Admin;

use App\Models\AiRecognitionLog;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AiProviderPerformanceChart extends ChartWidget
{
    protected static ?string $heading = 'Performance par provider IA';

    protected static ?int $sort = 9;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $providers = AiRecognitionLog::query()
            ->select(
                'provider',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN selected_action IN (\'matched\', \'unexpected\') THEN 1 ELSE 0 END) as confirmed'),
                DB::raw('SUM(CASE WHEN selected_action = \'dismissed\' THEN 1 ELSE 0 END) as dismissed'),
                DB::raw('SUM(CASE WHEN selected_action IS NULL THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN used_fallback = true THEN 1 ELSE 0 END) as fallback_count'),
                DB::raw('AVG(latency_ms) as avg_latency'),
                DB::raw('SUM(estimated_cost_usd) as total_cost'),
            )
            ->groupBy('provider')
            ->get()
            ->keyBy('provider');

        $providerNames = ['gemini' => 'Gemini Flash', 'openai' => 'GPT-4o'];
        $labels = [];
        $totalData = [];
        $confirmedData = [];
        $dismissedData = [];

        foreach (['gemini', 'openai'] as $key) {
            $labels[] = ($providerNames[$key] ?? $key)
                . ($providers->has($key) ? ' (' . number_format($providers[$key]->avg_latency, 0) . 'ms)' : '');
            $totalData[] = $providers->get($key)?->total ?? 0;
            $confirmedData[] = $providers->get($key)?->confirmed ?? 0;
            $dismissedData[] = $providers->get($key)?->dismissed ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Confirmées',
                    'data' => $confirmedData,
                    'backgroundColor' => '#10b981',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Rejetées',
                    'data' => $dismissedData,
                    'backgroundColor' => '#ef4444',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'En attente',
                    'data' => array_map(
                        fn ($total, $conf, $dis) => $total - $conf - $dis,
                        $totalData,
                        $confirmedData,
                        $dismissedData
                    ),
                    'backgroundColor' => '#94a3b8',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }

    public function getDescription(): ?string
    {
        $gemini = AiRecognitionLog::where('provider', 'gemini');
        $openai = AiRecognitionLog::where('provider', 'openai');

        $geminiCost = (clone $gemini)->sum('estimated_cost_usd');
        $openaiCost = (clone $openai)->sum('estimated_cost_usd');

        return 'Coût — Gemini : ' . number_format($geminiCost, 4) . ' $ · GPT-4o : ' . number_format($openaiCost, 4) . ' $';
    }
}
