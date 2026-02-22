<?php

namespace App\Filament\Widgets\Admin;

use App\Models\Organization;
use App\Models\Plan;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Laravel\Paddle\Subscription;

class SubscriptionStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $totalOrgs = Organization::withoutGlobalScopes()->count();

        $activeSubscriptions = Subscription::query()
            ->where('status', 'active')
            ->count();

        $trialingOrgs = Organization::withoutGlobalScopes()
            ->whereHas('customer', function ($query) {
                $query->where('trial_ends_at', '>', now());
            })
            ->count();

        // MRR calculation
        $mrr = 0;
        $plans = Plan::withCount(['organizations'])->get();
        foreach ($plans as $plan) {
            $mrr += ($plan->price_monthly * $plan->organizations_count);
        }

        return [
            Stat::make('Organisations', number_format($totalOrgs))
                ->description('Total')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),

            Stat::make('Abonnements actifs', number_format($activeSubscriptions))
                ->description('Payants')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('success'),

            Stat::make('Essais en cours', number_format($trialingOrgs))
                ->description('Période d\'essai')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            Stat::make('MRR estimé', number_format($mrr / 100, 2, ',', ' ') . ' €')
                ->description('Revenu mensuel récurrent')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('warning'),
        ];
    }
}
