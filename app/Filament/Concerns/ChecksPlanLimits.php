<?php

namespace App\Filament\Concerns;

use App\Enums\PlanFeature;
use App\Filament\App\Pages\Subscription;
use App\Services\PlanLimitService;
use Filament\Facades\Filament;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;

trait ChecksPlanLimits
{
    abstract protected static function getPlanFeature(): ?PlanFeature;

    public function mountChecksPlanLimits(): void
    {
        $feature = static::getPlanFeature();

        if (! $feature) {
            return;
        }

        $tenant = Filament::getTenant();

        if (! $tenant) {
            return;
        }

        $service = app(PlanLimitService::class);

        if (! $service->canCreate($tenant, $feature)) {
            Notification::make()
                ->title('Limite du plan atteinte')
                ->body($service->getLimitReachedMessage($feature, $tenant))
                ->warning()
                ->persistent()
                ->actions([
                    Action::make('upgrade')
                        ->label('Voir les plans')
                        ->url(Subscription::getUrl(tenant: $tenant))
                        ->button()
                        ->color('primary'),
                ])
                ->send();

            $this->redirect(static::getResource()::getUrl('index'));
        }
    }
}
