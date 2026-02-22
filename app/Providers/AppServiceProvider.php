<?php

namespace App\Providers;

use App\Listeners\HandlePaddleSubscription;
use App\Services\PlanLimitService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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

        RateLimiter::for('ai-vision', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->organization_id ?? $request->ip());
        });
    }
}
