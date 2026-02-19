<?php

namespace App\Filament\Widgets\Admin;

use App\Models\Asset;
use App\Models\Organization;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalOrgs = Organization::count();
        $newOrgsThisMonth = Organization::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $totalUsers = User::count();
        $newUsersThisMonth = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $totalAssets = Asset::count();

        $avgAssetsPerOrg = $totalOrgs > 0
            ? round($totalAssets / $totalOrgs, 1)
            : 0;

        return [
            Stat::make('Organisations', number_format($totalOrgs))
                ->description($newOrgsThisMonth > 0 ? "+{$newOrgsThisMonth} ce mois" : 'Aucune nouvelle ce mois')
                ->descriptionIcon($newOrgsThisMonth > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus')
                ->color($newOrgsThisMonth > 0 ? 'success' : 'gray'),

            Stat::make('Utilisateurs', number_format($totalUsers))
                ->description($newUsersThisMonth > 0 ? "+{$newUsersThisMonth} ce mois" : 'Aucun nouveau ce mois')
                ->descriptionIcon($newUsersThisMonth > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus')
                ->color($newUsersThisMonth > 0 ? 'info' : 'gray'),

            Stat::make('Assets gérés', number_format($totalAssets))
                ->description('Toutes organisations')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('Moy. assets/org', number_format($avgAssetsPerOrg, 1))
                ->description($totalOrgs . ' organisations actives')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('warning'),
        ];
    }
}
