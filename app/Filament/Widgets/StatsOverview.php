<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Models\Assignment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalAssets = Asset::count();
        $newThisMonth = Asset::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $activeAssignments = Assignment::active()->count();

        $overdueAssignments = Assignment::active()
            ->whereNotNull('expected_return_at')
            ->where('expected_return_at', '<', now())
            ->count();

        $totalValue = Asset::sum('purchase_cost');

        return [
            Stat::make('Total Assets', number_format($totalAssets))
                ->description($newThisMonth > 0 ? "+{$newThisMonth} ce mois" : 'Aucun ajout ce mois')
                ->descriptionIcon($newThisMonth > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus')
                ->color($newThisMonth > 0 ? 'success' : 'gray'),

            Stat::make('Assignments actives', number_format($activeAssignments))
                ->description('En cours')
                ->descriptionIcon('heroicon-m-user')
                ->color('info'),

            Stat::make('Retards', number_format($overdueAssignments))
                ->description($overdueAssignments > 0 ? 'Retours en retard' : 'Aucun retard')
                ->descriptionIcon($overdueAssignments > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdueAssignments > 0 ? 'danger' : 'success'),

            Stat::make('Valeur du parc', number_format($totalValue, 0, ',', ' ') . ' €')
                ->description('Coût d\'acquisition total')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('primary'),
        ];
    }
}
