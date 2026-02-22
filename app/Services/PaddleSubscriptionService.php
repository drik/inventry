<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Models\Organization;
use App\Models\Plan;
use Laravel\Paddle\Checkout;

class PaddleSubscriptionService
{
    public function __construct(
        protected PlanLimitService $planLimitService,
    ) {}

    /**
     * Create a Paddle checkout for subscribing to a plan.
     */
    public function createCheckout(
        Organization $organization,
        Plan $plan,
        BillingCycle $cycle,
        string $returnUrl,
    ): Checkout {
        $priceId = $cycle === BillingCycle::Monthly
            ? $plan->paddle_monthly_price_id
            : $plan->paddle_yearly_price_id;

        return $organization
            ->subscribe($priceId, 'default')
            ->returnTo($returnUrl);
    }

    /**
     * Swap to a different plan.
     */
    public function changePlan(
        Organization $organization,
        Plan $newPlan,
        BillingCycle $cycle,
    ): void {
        $priceId = $cycle === BillingCycle::Monthly
            ? $newPlan->paddle_monthly_price_id
            : $newPlan->paddle_yearly_price_id;

        $organization->subscription()->swap($priceId);
        $organization->update(['plan_id' => $newPlan->id]);
        $this->planLimitService->clearCache($organization);
    }

    /**
     * Cancel subscription at end of billing period.
     */
    public function cancel(Organization $organization): void
    {
        $organization->subscription()->cancel();
    }

    /**
     * Cancel subscription immediately.
     */
    public function cancelNow(Organization $organization): void
    {
        $organization->subscription()->cancelNow();
        $this->downgradeToFree($organization);
    }

    /**
     * Pause subscription.
     */
    public function pause(Organization $organization): void
    {
        $organization->subscription()->pause();
    }

    /**
     * Resume a paused subscription.
     */
    public function resume(Organization $organization): void
    {
        $organization->subscription()->resume();
    }

    /**
     * Start a generic trial (no credit card required).
     */
    public function startGenericTrial(Organization $organization, Plan $plan, int $trialDays = 14): void
    {
        $organization->createAsCustomer([
            'trial_ends_at' => now()->addDays($trialDays),
        ]);
        $organization->update(['plan_id' => $plan->id]);
        $this->planLimitService->clearCache($organization);
    }

    /**
     * Downgrade to the free plan.
     */
    public function downgradeToFree(Organization $organization): void
    {
        $freePlan = Plan::where('slug', 'freemium')->firstOrFail();

        if ($organization->subscribed()) {
            $organization->subscription()->cancelNow();
        }

        $organization->update(['plan_id' => $freePlan->id]);
        $this->planLimitService->clearCache($organization);
    }
}
