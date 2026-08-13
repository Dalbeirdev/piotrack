<?php

namespace App\Services;

use App\Models\File;
use App\Models\Organization;

/**
 * Setup checklist (ONBD-013). Steps are derived from real state, so progress is
 * always accurate and inherently resumable (ONBD-014) — nothing to persist.
 */
class OnboardingChecklist
{
    /**
     * @return array{steps: list<array{key: string, label: string, done: bool, url: string}>, complete: bool}
     */
    public function for(Organization $organization): array
    {
        $subscription = $organization->activeSubscription();
        $onPaidPlan = $subscription !== null && $subscription->status === 'active';
        $memberCount = $organization->members()->wherePivot('status', 'active')->count();
        $hasBillingEmail = $organization->billingProfile()->whereNotNull('billing_email')->exists();
        $hasFile = File::withoutGlobalScope('tenant')->where('organization_id', $organization->id)->exists();

        $steps = [
            ['key' => 'create_org', 'label' => 'Create your organization', 'done' => true, 'url' => '/settings/organization'],
            ['key' => 'choose_plan', 'label' => 'Choose a plan', 'done' => $onPaidPlan, 'url' => '/billing/plans'],
            ['key' => 'invite_team', 'label' => 'Invite your team', 'done' => $memberCount > 1, 'url' => '/settings/members'],
            ['key' => 'billing_details', 'label' => 'Add your billing details', 'done' => $hasBillingEmail, 'url' => '/billing'],
            ['key' => 'upload_asset', 'label' => 'Upload a brand asset', 'done' => $hasFile, 'url' => '/settings/files'],
        ];

        $complete = collect($steps)->every(fn ($s) => $s['done']);

        return ['steps' => $steps, 'complete' => $complete];
    }
}
