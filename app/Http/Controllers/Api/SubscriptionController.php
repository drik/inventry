<?php

namespace App\Http\Controllers\Api;

use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected PlanLimitService $planLimitService,
    ) {}

    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $org = $user->organization;

        if (! $org) {
            return response()->json(['message' => 'No organization found'], 404);
        }

        $plan = $this->planLimitService->getEffectivePlan($org);
        $isSubscribed = $org->subscribed();
        $onTrial = $org->onGenericTrial();

        return response()->json([
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
            ],
            'subscription' => [
                'is_subscribed' => $isSubscribed,
                'on_trial' => $onTrial,
                'trial_ends_at' => $org->customer?->trial_ends_at?->toISOString(),
                'status' => $isSubscribed ? $org->subscription()?->paddle_status : ($onTrial ? 'trialing' : 'free'),
            ],
        ]);
    }

    public function plans(): JsonResponse
    {
        $plans = Plan::active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'formatted_monthly_price' => $plan->formatted_monthly_price,
                'formatted_yearly_price' => $plan->formatted_yearly_price,
                'limits' => $plan->limits,
            ]);

        return response()->json(['plans' => $plans]);
    }

    public function usage(Request $request): JsonResponse
    {
        $user = $request->user();
        $org = $user->organization;

        if (! $org) {
            return response()->json(['message' => 'No organization found'], 404);
        }

        $features = [
            PlanFeature::MaxUsers,
            PlanFeature::MaxAssets,
            PlanFeature::MaxLocations,
            PlanFeature::MaxActiveInventorySessions,
            PlanFeature::MaxAiRequestsDaily,
            PlanFeature::MaxAiRequestsMonthly,
        ];

        $usage = [];
        foreach ($features as $feature) {
            $stats = $this->planLimitService->getUsageStats($org, $feature);
            $usage[$feature->value] = [
                'label' => $feature->getLabel(),
                ...$stats,
            ];
        }

        $booleanFeatures = [
            PlanFeature::HasApiAccess,
            PlanFeature::HasExport,
            PlanFeature::HasAdvancedAnalytics,
            PlanFeature::HasCustomIntegrations,
            PlanFeature::HasPrioritySupport,
        ];

        $featureAccess = [];
        foreach ($booleanFeatures as $feature) {
            $featureAccess[$feature->value] = [
                'label' => $feature->getLabel(),
                'enabled' => $this->planLimitService->hasFeature($org, $feature),
            ];
        }

        return response()->json([
            'usage' => $usage,
            'features' => $featureAccess,
        ]);
    }
}
