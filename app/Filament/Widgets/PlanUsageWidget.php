<?php

namespace App\Filament\Widgets;

use App\Enums\PlanFeature;
use App\Services\PlanLimitService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class PlanUsageWidget extends Widget
{
    protected static ?int $sort = 0;

    protected static string $view = 'filament.widgets.plan-usage';

    protected int|string|array $columnSpan = 'full';

    public function getUsageData(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return [];
        }

        $service = app(PlanLimitService::class);
        $plan = $service->getEffectivePlan($tenant);

        $features = [
            PlanFeature::MaxAssets,
            PlanFeature::MaxUsers,
            PlanFeature::MaxLocations,
            PlanFeature::MaxActiveInventorySessions,
        ];

        $data = [];
        foreach ($features as $feature) {
            $stats = $service->getUsageStats($tenant, $feature);
            $data[] = [
                'label' => $feature->getLabel(),
                'current' => $stats['current'],
                'limit' => $stats['limit'],
                'percentage' => $stats['percentage'],
                'is_unlimited' => $stats['is_unlimited'],
                'is_disabled' => $stats['is_disabled'],
                'icon' => match ($feature) {
                    PlanFeature::MaxAssets => 'heroicon-o-cube',
                    PlanFeature::MaxUsers => 'heroicon-o-users',
                    PlanFeature::MaxLocations => 'heroicon-o-map-pin',
                    PlanFeature::MaxActiveInventorySessions => 'heroicon-o-clipboard-document-list',
                    default => 'heroicon-o-chart-bar',
                },
            ];
        }

        return [
            'plan_name' => $plan->name,
            'features' => $data,
        ];
    }
}
