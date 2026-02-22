<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Admin\AiProviderPerformanceChart;
use App\Filament\Widgets\Admin\AiRecentLogsTable;
use App\Filament\Widgets\Admin\AiRequestsByPlanChart;
use App\Filament\Widgets\Admin\AiStatsOverview;
use App\Filament\Widgets\Admin\AiUsageTrendChart;
use Filament\Pages\Dashboard;

class AiDashboard extends Dashboard
{
    protected static string $routePath = '/ai-usage';

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Dashboards';

    protected static ?string $navigationLabel = 'IA & Vision';

    protected static ?string $title = 'Dashboard IA & Vision';

    protected static ?int $navigationSort = 2;

    public function getWidgets(): array
    {
        return [
            AiStatsOverview::class,
            AiRequestsByPlanChart::class,
            AiProviderPerformanceChart::class,
            AiUsageTrendChart::class,
            AiRecentLogsTable::class,
        ];
    }
}
