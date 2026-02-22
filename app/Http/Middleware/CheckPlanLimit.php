<?php

namespace App\Http\Middleware;

use App\Enums\PlanFeature;
use App\Services\PlanLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimit
{
    public function __construct(
        protected PlanLimitService $planLimitService,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user || ! $user->organization) {
            return $next($request);
        }

        $planFeature = PlanFeature::tryFrom($feature);

        if (! $planFeature) {
            return $next($request);
        }

        $org = $user->organization;

        if (! $this->planLimitService->canCreate($org, $planFeature)) {
            return response()->json([
                'message' => $this->planLimitService->getLimitReachedMessage($planFeature, $org),
                'error' => 'plan_limit_reached',
                'feature' => $feature,
            ], 403);
        }

        return $next($request);
    }
}
