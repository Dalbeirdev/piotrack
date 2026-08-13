<?php

use App\Models\AuditLog;

/*
 * Closes the Stage 3 recurring-billing debt (BILL-011/012/016/017) via the
 * scheduled commands (JOBS).
 */

it('renews an active subscription past its period end', function () {
    [$org] = makeOrganization();
    $sub = $org->activeSubscription();
    $sub->forceFill([
        'status' => 'active',
        'trial_ends_at' => null,
        'current_period_start' => now()->subMonth(),
        'current_period_end' => now()->subDay(),
    ])->save();

    $this->artisan('subscriptions:process-renewals')->assertSuccessful();

    $sub->refresh();
    expect($sub->status)->toBe('active')
        ->and($sub->current_period_end->isFuture())->toBeTrue()
        ->and($org->invoices()->where('status', 'paid')->count())->toBe(1);
    expect(AuditLog::where('action', 'subscription.renewed')->exists())->toBeTrue();
});

it('ends a scheduled-to-cancel subscription at period end instead of renewing', function () {
    [$org] = makeOrganization();
    $sub = $org->activeSubscription();
    $sub->forceFill([
        'status' => 'active',
        'trial_ends_at' => null,
        'current_period_end' => now()->subDay(),
        'cancel_at_period_end' => true,
    ])->save();

    $this->artisan('subscriptions:process-renewals')->assertSuccessful();

    expect($org->activeSubscription())->toBeNull(); // canceled
    expect($org->invoices()->count())->toBe(0); // no renewal invoice
});

it('expires a lapsed trial', function () {
    [$org] = makeOrganization();
    $org->activeSubscription()->forceFill(['trial_ends_at' => now()->subDay()])->save();

    $this->artisan('subscriptions:expire-trials')->assertSuccessful();

    expect($org->subscriptions()->latest('id')->first()->status)->toBe('expired');
});

it('suspends a past-due subscription whose grace has elapsed', function () {
    [$org] = makeOrganization();
    $org->activeSubscription()->forceFill([
        'status' => 'past_due',
        'ends_at' => now()->subDay(),
    ])->save();

    $this->artisan('subscriptions:enforce-grace')->assertSuccessful();

    expect($org->subscriptions()->latest('id')->first()->status)->toBe('suspended');
});

it('notifies owners of a trial ending soon', function () {
    Illuminate\Support\Facades\Notification::fake();
    [$org, $owner] = makeOrganization();
    $org->activeSubscription()->forceFill(['trial_ends_at' => now()->addDays(2)])->save();

    $this->artisan('subscriptions:notify-trial-ending')->assertSuccessful();

    Illuminate\Support\Facades\Notification::assertSentTo($owner, App\Notifications\TrialEndingNotification::class);
});
