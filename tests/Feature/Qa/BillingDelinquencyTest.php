<?php

declare(strict_types=1);

/**
 * QA §16/§17/§54/§55 - the delinquency chain, end to end.
 *
 * Existing coverage asserts the pieces: a webhook produces past_due, and
 * enforce-grace suspends a subscription whose ends_at has elapsed. But the
 * scheduler test force-fills that state directly, so nothing joins the two.
 * If markPastDue ever stopped setting ends_at, enforce-grace would silently
 * match nothing, the grace period would never end, and both existing tests
 * would still pass - non-payment would cost a customer nothing, forever.
 *
 * This drives the whole chain through real entry points and then asks the
 * question that actually matters commercially: once suspended, is the product
 * genuinely restricted at the backend?
 *
 * Organization: Acme Managed IT Services on Professional.
 */

use App\Billing\Entitlements;
use App\Billing\Feature;
use App\Models\Contact;
use App\Support\CurrentOrganization;
use Illuminate\Testing\TestResponse;

function webhook(array $body): TestResponse
{
    return test()->postJson('/webhooks/manual', $body, ['X-Billing-Signature' => 'local-manual-secret']);
}

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'professional');
    $this->subscription = $this->org->activeSubscription();
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('runs the full delinquency chain from failed payment to revoked access', function () {
    $entitlements = app(Entitlements::class);

    // -- 1. Healthy: marketing is available -------------------------------
    expect($entitlements->feature($this->org, Feature::Marketing))->toBeTrue();
    $this->actingAs($this->owner)->get(route('marketing.dashboard'))->assertSuccessful();

    // -- 2. Payment fails -------------------------------------------------
    webhook([
        'id' => 'evt_acme_pf_1',
        'type' => 'subscription.payment_failed',
        'data' => ['subscription_id' => $this->subscription->id],
    ])->assertOk()->assertJsonPath('status', 'processed');

    $this->subscription->refresh();

    expect($this->subscription->status)->toBe('past_due')
        // The grace deadline must be set here, or enforce-grace can never find it.
        ->and($this->subscription->ends_at)->not->toBeNull()
        ->and($this->subscription->ends_at->isFuture())->toBeTrue();

    // -- 3. During grace, access continues --------------------------------
    $entitlements->forget($this->org);
    expect($entitlements->feature($this->org->fresh(), Feature::Marketing))->toBeTrue();
    $this->actingAs($this->owner)->get(route('marketing.dashboard'))->assertSuccessful();

    // -- 4. Grace has not elapsed: enforce-grace must NOT suspend ----------
    $this->artisan('subscriptions:enforce-grace')->assertSuccessful();

    expect($this->subscription->refresh()->status)->toBe('past_due');

    // -- 5. Grace elapses --------------------------------------------------
    $this->subscription->forceFill(['ends_at' => now()->subMinute()])->save();
    $this->artisan('subscriptions:enforce-grace')->assertSuccessful();

    expect($this->subscription->refresh()->status)->toBe('suspended');

    // -- 6. Access is genuinely revoked at the backend ---------------------
    $entitlements->forget($this->org);
    $org = $this->org->fresh();

    expect($org->activeSubscription())->toBeNull()
        ->and($entitlements->feature($org, Feature::Marketing))->toBeFalse()
        ->and($entitlements->feature($org, Feature::Analytics))->toBeFalse();

    $this->actingAs($this->owner)->get(route('marketing.dashboard'))->assertForbidden();
    $this->actingAs($this->owner)->get(route('analytics.dashboard'))->assertForbidden();
});

it('leaves a suspended organization on the restrictive free fallback, not wide open', function () {
    $this->subscription->forceFill(['status' => 'suspended'])->save();
    app(Entitlements::class)->forget($this->org);
    $org = $this->org->fresh();

    $entitlements = app(Entitlements::class);

    // CRM survives so the customer can still reach their own data.
    expect($entitlements->feature($org, Feature::Crm))->toBeTrue();
    $this->actingAs($this->owner)->get(route('crm.contacts.index'))->assertSuccessful();

    // Everything they were paying for does not.
    foreach ([Feature::Marketing, Feature::Analytics, Feature::Advertising, Feature::Seo, Feature::Ai] as $feature) {
        expect($entitlements->feature($org, $feature))->toBeFalse("{$feature->value} survived suspension");
    }
});

it('denies the API by entitlement, not only the web UI', function () {
    // §55: hiding navigation is not gating. The API carries its own entitlement.
    $token = $this->owner->createToken('qa')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/contacts')
        ->assertSuccessful();

    $this->subscription->forceFill(['status' => 'suspended'])->save();
    app(Entitlements::class)->forget($this->org);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/contacts')
        ->assertForbidden();
});

it('restores access on resubscription after suspension', function () {
    $this->subscription->forceFill(['status' => 'suspended'])->save();
    app(Entitlements::class)->forget($this->org);

    $this->actingAs($this->owner)->get(route('marketing.dashboard'))->assertForbidden();

    // The customer pays again.
    subscribeOrganization($this->org->fresh(), 'professional');
    app(Entitlements::class)->forget($this->org);

    expect(app(Entitlements::class)->feature($this->org->fresh(), Feature::Marketing))->toBeTrue();
    $this->actingAs($this->owner)->get(route('marketing.dashboard'))->assertSuccessful();
});

it('does not destroy data that exceeds a downgraded plan limit', function () {
    // §17 asks what happens when current usage exceeds the new plan's limits.
    app(CurrentOrganization::class)->set($this->org);

    foreach (range(1, 5) as $i) {
        Contact::create([
            'first_name' => 'Prospect', 'last_name' => (string) $i,
            'email' => "prospect{$i}@precisionmfg-test.com",
        ]);
    }

    $before = Contact::count();
    app(CurrentOrganization::class)->forget();

    $this->subscription->forceFill(['status' => 'suspended'])->save();
    app(Entitlements::class)->forget($this->org);

    app(CurrentOrganization::class)->set($this->org->fresh());

    // Losing the plan must never delete the customer's records.
    expect(Contact::count())->toBe($before);
});
