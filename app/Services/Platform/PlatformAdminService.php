<?php

namespace App\Services\Platform;

use App\Models\AiRequest;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;

/**
 * Cross-tenant rollups for the platform staff console (ADMIN-001/002/003).
 * Every query here deliberately crosses the tenant scope — this is the one
 * place in the product that is allowed to, and it is reachable only by platform
 * staff holding `admin.platform`.
 */
class PlatformAdminService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return [
            'organizations' => Organization::count(),
            'users' => User::count(),
            'active_subscriptions' => Subscription::whereIn('status', ['active', 'trialing'])->count(),
            'trialing' => Subscription::where('status', 'trialing')->count(),
            'past_due' => Subscription::where('status', 'past_due')->count(),
            'canceled' => Subscription::where('status', 'canceled')->count(),
            'mrr' => $this->monthlyRecurringRevenue(),
            'contacts' => Contact::withoutGlobalScope('tenant')->count(),
            'ai_requests' => AiRequest::withoutGlobalScope('tenant')->count(),
            'ai_spend' => (int) AiRequest::withoutGlobalScope('tenant')->sum('estimated_cost'),
        ];
    }

    /**
     * Tenants with their plan, status and seat usage.
     *
     * @return list<array<string, mixed>>
     */
    public function tenants(int $limit = 100): array
    {
        return Organization::query()
            ->withCount('members')
            ->latest('id')->limit($limit)->get()
            ->map(function (Organization $organization) {
                $subscription = $organization->activeSubscription();

                return [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'members' => $organization->getAttribute('members_count'),
                    'plan' => $subscription?->plan->code,
                    'status' => $subscription?->status,
                    'trial_ends_at' => $subscription?->trial_ends_at?->toDateString(),
                    'created_at' => $organization->created_at?->toDateString(),
                ];
            })->all();
    }

    /**
     * Monthly recurring revenue across active subscriptions (minor units).
     * Annual plans are normalized to a monthly figure.
     */
    public function monthlyRecurringRevenue(): int
    {
        $mrr = 0;

        Subscription::with('plan.prices')
            ->whereIn('status', ['active'])
            ->get()
            ->each(function (Subscription $subscription) use (&$mrr) {
                $price = $subscription->plan?->prices->firstWhere('interval', $subscription->interval);

                if ($price === null) {
                    return;
                }

                $amount = (int) $price->amount * max(1, $subscription->quantity);
                $mrr += $subscription->interval === 'annual' ? (int) round($amount / 12) : $amount;
            });

        return $mrr;
    }
}
