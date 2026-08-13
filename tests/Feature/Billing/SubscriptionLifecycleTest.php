<?php

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\Plan;

it('starts every new organization on a Growth trial', function () {
    [$org] = makeOrganization();
    $sub = $org->activeSubscription();

    expect($sub)->not->toBeNull()
        ->and($sub->status)->toBe('trialing')
        ->and($sub->plan->code)->toBe('growth')
        ->and($sub->onTrial())->toBeTrue();
});

it('checks out a paid plan and generates a paid invoice (signup with plan)', function () {
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)
        ->post(route('billing.checkout.store'), ['plan' => 'professional', 'interval' => 'monthly'])
        ->assertRedirect(route('billing.index'));

    $sub = $org->activeSubscription();
    expect($sub->status)->toBe('active')->and($sub->plan->code)->toBe('professional');

    $invoice = $org->invoices()->latest('id')->first();
    expect($invoice->status)->toBe('paid')
        ->and($invoice->total)->toBe(34900)
        ->and($invoice->amount_paid)->toBe(34900);

    expect(AuditLog::where('action', 'subscription.activated')->exists())->toBeTrue();
    expect(AuditLog::where('action', 'invoice.paid')->exists())->toBeTrue();
});

it('upgrades with a prorated charge', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'starter');
    $invoicesBefore = $org->invoices()->count();

    $this->actingAs($owner)
        ->patch(route('billing.subscription.update'), ['plan' => 'professional', 'interval' => 'monthly'])
        ->assertRedirect(route('billing.index'));

    expect($org->activeSubscription()->plan->code)->toBe('professional');
    // A proration invoice was generated for the upgrade.
    expect($org->invoices()->count())->toBeGreaterThan($invoicesBefore);
    expect(AuditLog::where('action', 'subscription.plan_changed')->exists())->toBeTrue();
});

it('blocks a downgrade that would exceed the new plan member limit', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'growth'); // 10 members
    addMember($org, Role::Admin);
    addMember($org, Role::Admin);
    addMember($org, Role::Admin); // owner + 3 = 4 active members

    $this->actingAs($owner)
        ->patch(route('billing.subscription.update'), ['plan' => 'starter', 'interval' => 'monthly']) // limit 3
        ->assertSessionHasErrors('plan');

    expect($org->activeSubscription()->plan->code)->toBe('growth');
});

it('allows a downgrade that stays within limits', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'professional');

    $this->actingAs($owner)
        ->patch(route('billing.subscription.update'), ['plan' => 'starter', 'interval' => 'monthly'])
        ->assertRedirect();

    expect($org->activeSubscription()->plan->code)->toBe('starter');
});

it('schedules a cancellation and resumes it', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'growth');

    $this->actingAs($owner)->post(route('billing.subscription.cancel'), ['immediately' => false])->assertRedirect();
    $sub = $org->activeSubscription();
    expect($sub->cancel_at_period_end)->toBeTrue()->and($sub->status)->toBe('active');
    expect(AuditLog::where('action', 'subscription.cancellation_scheduled')->exists())->toBeTrue();

    $this->actingAs($owner)->post(route('billing.subscription.resume'))->assertRedirect();
    expect($org->activeSubscription()->cancel_at_period_end)->toBeFalse();
    expect(AuditLog::where('action', 'subscription.resumed')->exists())->toBeTrue();
});

it('cancels immediately', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'growth');

    $this->actingAs($owner)->post(route('billing.subscription.cancel'), ['immediately' => true])->assertRedirect();

    expect($org->activeSubscription())->toBeNull(); // canceled is not an active state
    expect(AuditLog::where('action', 'subscription.canceled')->exists())->toBeTrue();
});

it('changes seat quantity with proration', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'professional');

    $this->actingAs($owner)
        ->patch(route('billing.subscription.update'), ['plan' => 'professional', 'interval' => 'monthly', 'quantity' => 3])
        ->assertRedirect();

    expect($org->activeSubscription()->quantity)->toBe(3);
    expect(AuditLog::where('action', 'subscription.quantity_changed')->exists())->toBeTrue();
});

it('requires billing.manage to change a subscription', function () {
    [$org] = makeOrganization();
    $viewer = addMember($org, Role::Viewer);
    subscribeOrganization($org, 'growth');

    $this->actingAs($viewer)
        ->patch(route('billing.subscription.update'), ['plan' => 'starter', 'interval' => 'monthly'])
        ->assertForbidden();
});

it('rejects checkout of the custom-priced enterprise plan', function () {
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)
        ->post(route('billing.checkout.store'), ['plan' => 'enterprise', 'interval' => 'monthly'])
        ->assertStatus(422);

    expect(Plan::where('code', 'enterprise')->exists())->toBeTrue();
});
