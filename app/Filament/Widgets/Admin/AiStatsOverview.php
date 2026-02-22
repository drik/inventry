<?php

namespace App\Filament\Widgets\Admin;

use App\Models\AiRecognitionLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 7;

    protected function getStats(): array
    {
        $totalRequests = AiRecognitionLog::count();
        $todayRequests = AiRecognitionLog::whereDate('created_at', today())->count();
        $thisMonthRequests = AiRecognitionLog::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Success rate: requests where user confirmed a match (selected_action is not null)
        $confirmedCount = AiRecognitionLog::whereNotNull('selected_action')
            ->where('selected_action', '!=', 'dismissed')
            ->count();
        $resolvedCount = AiRecognitionLog::whereNotNull('selected_action')->count();
        $successRate = $resolvedCount > 0 ? round(($confirmedCount / $resolvedCount) * 100, 1) : 0;

        // Total cost
        $totalCost = AiRecognitionLog::sum('estimated_cost_usd');

        // Fallback rate
        $fallbackCount = AiRecognitionLog::where('used_fallback', true)->count();
        $fallbackRate = $totalRequests > 0 ? round(($fallbackCount / $totalRequests) * 100, 1) : 0;

        // Avg latency
        $avgLatency = AiRecognitionLog::whereNotNull('latency_ms')->avg('latency_ms');

        return [
            Stat::make('Requêtes IA totales', number_format($totalRequests))
                ->description($todayRequests . ' aujourd\'hui · ' . $thisMonthRequests . ' ce mois')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('primary'),

            Stat::make('Taux de confirmation', $successRate . '%')
                ->description($confirmedCount . ' confirmés / ' . $resolvedCount . ' résolus')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($successRate >= 70 ? 'success' : ($successRate >= 50 ? 'warning' : 'danger')),

            Stat::make('Coût total estimé', number_format($totalCost, 4) . ' $')
                ->description('Fallback : ' . $fallbackRate . '% (' . $fallbackCount . ' req.)')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('warning'),

            Stat::make('Latence moyenne', $avgLatency ? number_format($avgLatency, 0) . ' ms' : 'N/A')
                ->description($avgLatency ? ($avgLatency < 3000 ? 'Bon' : 'Lent') : 'Aucune donnée')
                ->descriptionIcon('heroicon-m-clock')
                ->color($avgLatency && $avgLatency < 3000 ? 'success' : 'info'),
        ];
    }
}
