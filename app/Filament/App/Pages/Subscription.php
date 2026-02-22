<?php

namespace App\Filament\App\Pages;

use App\Enums\BillingCycle;
use App\Enums\PlanFeature;
use App\Models\Plan;
use App\Services\PaddleSubscriptionService;
use App\Services\PlanLimitService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Subscription extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Abonnement';

    protected static ?string $title = 'Abonnement';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.app.pages.subscription';

    public ?string $selectedCycle = 'monthly';

    public function mount(): void
    {
        // Only organization owner/admin can access
        $user = auth()->user();
        $tenant = Filament::getTenant();

        if (! $tenant || $tenant->owner_id !== $user->id) {
            if (! in_array($user->role->value, ['super_admin', 'admin'])) {
                $this->redirect(Pages\Dashboard::getUrl());
            }
        }
    }

    public function getPlansProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return Plan::active()->orderBy('sort_order')->get();
    }

    public function getCurrentPlanProperty(): ?Plan
    {
        $tenant = Filament::getTenant();
        $service = app(PlanLimitService::class);

        return $service->getEffectivePlan($tenant);
    }

    public function getUsageStatsProperty(): array
    {
        $tenant = Filament::getTenant();
        $service = app(PlanLimitService::class);

        $features = [
            PlanFeature::MaxUsers,
            PlanFeature::MaxAssets,
            PlanFeature::MaxLocations,
            PlanFeature::MaxActiveInventorySessions,
            PlanFeature::MaxAiRequestsDaily,
            PlanFeature::MaxAiRequestsMonthly,
        ];

        $stats = [];
        foreach ($features as $feature) {
            $stats[$feature->value] = $service->getUsageStats($tenant, $feature);
            $stats[$feature->value]['label'] = $feature->getLabel();
        }

        return $stats;
    }

    public function getSubscriptionStatusProperty(): ?string
    {
        $tenant = Filament::getTenant();

        if ($tenant->subscribed()) {
            $subscription = $tenant->subscription();
            if ($subscription->onGracePeriod()) {
                return 'cancelling';
            }
            if ($subscription->paused()) {
                return 'paused';
            }

            return 'active';
        }

        if ($tenant->onGenericTrial()) {
            return 'trialing';
        }

        return 'free';
    }

    public function switchCycle(string $cycle): void
    {
        $this->selectedCycle = $cycle;
    }

    public function cancelSubscription(): void
    {
        $tenant = Filament::getTenant();
        $service = app(PaddleSubscriptionService::class);

        try {
            $service->cancel($tenant);
            Notification::make()
                ->title('Abonnement annulé')
                ->body('Votre abonnement sera actif jusqu\'à la fin de la période de facturation.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur')
                ->body('Impossible d\'annuler l\'abonnement. Veuillez réessayer.')
                ->danger()
                ->send();
        }
    }

    public function resumeSubscription(): void
    {
        $tenant = Filament::getTenant();
        $service = app(PaddleSubscriptionService::class);

        try {
            $service->resume($tenant);
            Notification::make()
                ->title('Abonnement repris')
                ->body('Votre abonnement a été réactivé.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur')
                ->body('Impossible de reprendre l\'abonnement. Veuillez réessayer.')
                ->danger()
                ->send();
        }
    }

    public function pauseSubscription(): void
    {
        $tenant = Filament::getTenant();
        $service = app(PaddleSubscriptionService::class);

        try {
            $service->pause($tenant);
            Notification::make()
                ->title('Abonnement en pause')
                ->body('Votre abonnement a été mis en pause.')
                ->warning()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur')
                ->body('Impossible de mettre en pause l\'abonnement.')
                ->danger()
                ->send();
        }
    }

    public function changePlan(string $planSlug): void
    {
        $tenant = Filament::getTenant();
        $service = app(PaddleSubscriptionService::class);
        $plan = Plan::where('slug', $planSlug)->firstOrFail();
        $cycle = BillingCycle::from($this->selectedCycle);

        try {
            $service->changePlan($tenant, $plan, $cycle);
            Notification::make()
                ->title('Plan modifié')
                ->body("Vous êtes maintenant sur le plan {$plan->name}.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur')
                ->body('Impossible de changer de plan. Veuillez réessayer.')
                ->danger()
                ->send();
        }
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return in_array($user->role->value, ['super_admin', 'admin']);
    }
}
