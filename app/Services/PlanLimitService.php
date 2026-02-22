<?php

namespace App\Services;

use App\Enums\InventorySessionStatus;
use App\Enums\PlanFeature;
use App\Models\AiUsageLog;
use App\Models\Asset;
use App\Models\InventorySession;
use App\Models\InventoryTask;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PlanLimitService
{
    public function getEffectivePlan(Organization $org): Plan
    {
        return Cache::remember(
            "org:{$org->id}:plan",
            3600,
            fn () => $org->plan ?? Plan::where('slug', 'freemium')->first()
        );
    }

    public function getLimit(Organization $org, PlanFeature $feature): int
    {
        $plan = $this->getEffectivePlan($org);

        return $plan->getLimit($feature->value);
    }

    public function getCurrentUsage(Organization $org, PlanFeature $feature, ?string $parentId = null): int
    {
        return match ($feature) {
            PlanFeature::MaxUsers => User::where('organization_id', $org->id)->count(),
            PlanFeature::MaxAssets => Asset::withoutGlobalScopes()->where('organization_id', $org->id)->count(),
            PlanFeature::MaxLocations => Location::withoutGlobalScopes()->where('organization_id', $org->id)->count(),
            PlanFeature::MaxActiveInventorySessions => InventorySession::withoutGlobalScopes()
                ->where('organization_id', $org->id)
                ->whereIn('status', [InventorySessionStatus::Draft->value, InventorySessionStatus::InProgress->value])
                ->count(),
            PlanFeature::MaxTasksPerSession => $parentId
                ? InventoryTask::withoutGlobalScopes()->where('session_id', $parentId)->count()
                : 0,
            PlanFeature::MaxAiRequestsDaily => AiUsageLog::where('organization_id', $org->id)
                ->whereDate('created_at', today())
                ->count(),
            PlanFeature::MaxAiRequestsMonthly => AiUsageLog::where('organization_id', $org->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            PlanFeature::MaxOrganizations => 0, // Handled separately via canCreateOrganization
            default => 0,
        };
    }

    public function canCreate(Organization $org, PlanFeature $feature, ?string $parentId = null): bool
    {
        $limit = $this->getLimit($org, $feature);

        // -1 means unlimited
        if ($limit === -1) {
            return true;
        }

        // 0 means disabled
        if ($limit === 0) {
            return false;
        }

        $currentUsage = $this->getCurrentUsage($org, $feature, $parentId);

        return $currentUsage < $limit;
    }

    public function hasFeature(Organization $org, PlanFeature $feature): bool
    {
        $plan = $this->getEffectivePlan($org);

        return $plan->hasFeature($feature->value);
    }

    public function getUsageStats(Organization $org, PlanFeature $feature, ?string $parentId = null): array
    {
        $limit = $this->getLimit($org, $feature);
        $current = $this->getCurrentUsage($org, $feature, $parentId);

        return [
            'current' => $current,
            'limit' => $limit,
            'is_unlimited' => $limit === -1,
            'is_disabled' => $limit === 0,
            'percentage' => $limit > 0 ? min(round(($current / $limit) * 100), 100) : ($limit === -1 ? 0 : 100),
            'remaining' => $limit === -1 ? null : max(0, $limit - $current),
            'can_create' => $this->canCreate($org, $feature, $parentId),
        ];
    }

    public function canCreateOrganization(User $user): bool
    {
        // Get the user's current organization to determine their plan
        $org = $user->organization;
        if (! $org) {
            return true; // First organization
        }

        $plan = $this->getEffectivePlan($org);
        $limit = $plan->getLimit(PlanFeature::MaxOrganizations->value);

        if ($limit === -1) {
            return true;
        }

        // Count all organizations owned by this user
        $currentCount = Organization::withoutGlobalScopes()
            ->where('owner_id', $user->id)
            ->count();

        return $currentCount < $limit;
    }

    public function getLimitReachedMessage(PlanFeature $feature, Organization $org): string
    {
        $plan = $this->getEffectivePlan($org);
        $limit = $this->getLimit($org, $feature);

        return "Limite atteinte : votre plan {$plan->name} autorise {$limit} {$feature->getLabel()}. Passez à un plan supérieur pour en ajouter davantage.";
    }

    public function clearCache(Organization $org): void
    {
        Cache::forget("org:{$org->id}:plan");
    }
}
