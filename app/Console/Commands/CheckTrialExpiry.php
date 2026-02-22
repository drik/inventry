<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Plan;
use App\Notifications\TrialEnding;
use App\Services\PlanLimitService;
use Illuminate\Console\Command;

class CheckTrialExpiry extends Command
{
    protected $signature = 'subscription:check-trial-expiry';

    protected $description = 'Check for expiring trials and send notifications';

    public function handle(PlanLimitService $planLimitService): int
    {
        $this->info('Checking trial expirations...');

        $freePlan = Plan::where('slug', 'freemium')->first();

        if (! $freePlan) {
            $this->error('Freemium plan not found. Run PlanSeeder first.');

            return self::FAILURE;
        }

        // Notify organizations 3 days before trial ends
        $expiringOrgs = Organization::withoutGlobalScopes()
            ->whereHas('customer', function ($query) {
                $query->whereBetween('trial_ends_at', [now(), now()->addDays(3)]);
            })
            ->get();

        foreach ($expiringOrgs as $org) {
            $trialEndsAt = $org->customer->trial_ends_at;
            $daysRemaining = now()->diffInDays($trialEndsAt);

            $owner = $org->owner;
            if ($owner) {
                $owner->notify(new TrialEnding($org, $daysRemaining));
                $this->line("  Notified {$owner->email} - trial ending in {$daysRemaining} days for {$org->name}");
            }
        }

        // Downgrade expired trials to Freemium
        $expiredOrgs = Organization::withoutGlobalScopes()
            ->whereHas('customer', function ($query) {
                $query->where('trial_ends_at', '<', now());
            })
            ->where(function ($query) use ($freePlan) {
                $query->whereNull('plan_id')
                    ->orWhere('plan_id', '!=', $freePlan->id);
            })
            ->whereDoesntHave('subscriptions', function ($query) {
                $query->where('status', 'active');
            })
            ->get();

        foreach ($expiredOrgs as $org) {
            $org->update(['plan_id' => $freePlan->id]);
            $planLimitService->clearCache($org);
            $this->line("  Downgraded {$org->name} to Freemium (trial expired)");
        }

        $this->info("Done. Notified {$expiringOrgs->count()} orgs, downgraded {$expiredOrgs->count()} orgs.");

        return self::SUCCESS;
    }
}
