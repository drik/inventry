<?php

namespace App\Listeners;

use App\Models\Plan;
use App\Services\PlanLimitService;
use Laravel\Paddle\Events\SubscriptionCanceled;
use Laravel\Paddle\Events\SubscriptionCreated;

class HandlePaddleSubscription
{
    public function __construct(
        protected PlanLimitService $planLimitService,
    ) {}

    public function handleCreated(SubscriptionCreated $event): void
    {
        $organization = $event->billable;

        $paddleSubscription = $event->subscription;
        $items = $paddleSubscription->items ?? [];

        // Get the price ID from the subscription items
        $paddlePriceId = null;
        if (! empty($items)) {
            $firstItem = is_array($items) ? ($items[0] ?? null) : $items->first();
            $paddlePriceId = $firstItem['price_id'] ?? ($firstItem->price_id ?? null);
        }

        if ($paddlePriceId) {
            $plan = Plan::where('paddle_monthly_price_id', $paddlePriceId)
                ->orWhere('paddle_yearly_price_id', $paddlePriceId)
                ->first();

            if ($plan) {
                $organization->update(['plan_id' => $plan->id]);
                $this->planLimitService->clearCache($organization);
            }
        }
    }

    public function handleCanceled(SubscriptionCanceled $event): void
    {
        $organization = $event->billable;
        $freePlan = Plan::where('slug', 'freemium')->first();

        if ($freePlan) {
            $organization->update(['plan_id' => $freePlan->id]);
            $this->planLimitService->clearCache($organization);
        }
    }
}
