<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Admin\OrganizationsGrowthChart;
use App\Filament\Widgets\Admin\PlatformAssetsStatusChart;
use App\Filament\Widgets\Admin\PlatformStatsOverview;
use App\Filament\Widgets\Admin\RecentOrganizationsTable;
use App\Filament\Widgets\Admin\SubscriptionStatsOverview;
use App\Filament\Widgets\Admin\TopOrganizationsChart;
use App\Filament\Widgets\Admin\UsersByRoleChart;
use Filament\Pages\Dashboard;

class GeneralDashboard extends Dashboard
{
    protected static string $routePath = '/';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationGroup = 'Dashboards';

    protected static ?string $navigationLabel = 'Général';

    protected static ?string $title = 'Dashboard Général';

    protected static ?int $navigationSort = 1;

    public function getWidgets(): array
    {
        return [
            SubscriptionStatsOverview::class,
            PlatformStatsOverview::class,
            OrganizationsGrowthChart::class,
            TopOrganizationsChart::class,
            UsersByRoleChart::class,
            PlatformAssetsStatusChart::class,
            RecentOrganizationsTable::class,
        ];
    }
}
