<?php

namespace App\Providers;

use App\Listeners\HandlePaddleSubscription;
use App\Services\PlanLimitService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Paddle\Events\SubscriptionCanceled;
use Laravel\Paddle\Events\SubscriptionCreated;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PlanLimitService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(SubscriptionCreated::class, [HandlePaddleSubscription::class, 'handleCreated']);
        Event::listen(SubscriptionCanceled::class, [HandlePaddleSubscription::class, 'handleCanceled']);
    }
}
