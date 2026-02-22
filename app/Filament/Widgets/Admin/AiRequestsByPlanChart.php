<?php

namespace App\Filament\Widgets\Admin;

use App\Models\AiRecognitionLog;
use App\Models\Plan;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AiRequestsByPlanChart extends ChartWidget
{
    protected static ?string $heading = 'Requêtes IA par plan';

    protected static ?int $sort = 8;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $plans = Plan::orderBy('sort_order')->get();
        $planMap = $plans->keyBy('id');

        // Count AI requests per plan via organization → plan_id
        $results = AiRecognitionLog::query()
            ->join('organizations', 'ai_recognition_logs.organization_id', '=', 'organizations.id')
            ->select('organizations.plan_id', DB::raw('COUNT(*) as total'))
            ->groupBy('organizations.plan_id')
            ->pluck('total', 'plan_id');

        // Count confirmed (matched/unexpected) per plan
        $confirmed = AiRecognitionLog::query()
            ->join('organizations', 'ai_recognition_logs.organization_id', '=', 'organizations.id')
            ->whereIn('ai_recognition_logs.selected_action', ['matched', 'unexpected'])
            ->select('organizations.plan_id', DB::raw('COUNT(*) as total'))
            ->groupBy('organizations.plan_id')
            ->pluck('total', 'plan_id');

        $labels = [];
        $totalData = [];
        $confirmedData = [];
        $colors = ['#94a3b8', '#3b82f6', '#8b5cf6', '#f59e0b'];

        foreach ($plans as $i => $plan) {
            $labels[] = $plan->name;
            $totalData[] = $results->get($plan->id, 0);
            $confirmedData[] = $confirmed->get($plan->id, 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total requêtes',
                    'data' => $totalData,
                    'backgroundColor' => $colors,
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Confirmées',
                    'data' => $confirmedData,
                    'backgroundColor' => '#10b981',
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
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
