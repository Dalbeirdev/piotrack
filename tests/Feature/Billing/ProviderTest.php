<?php

use App\Billing\Contracts\PaymentProvider;
use App\Billing\PaymentProviderManager;
use App\Billing\Providers\ManualPaymentProvider;
use App\Billing\Providers\StripePaymentProvider;

it('resolves the manual provider by default', function () {
    expect(app(PaymentProvider::class))->toBeInstanceOf(ManualPaymentProvider::class)
        ->and(app(PaymentProvider::class)->key())->toBe('manual');
});

it('can resolve the stripe driver by name', function () {
    // The driver is constructible without keys; it is only exercised against a
    // real Stripe sandbox (ADR-0003), never in this suite.
    expect(app(PaymentProviderManager::class)->driver('stripe'))->toBeInstanceOf(StripePaymentProvider::class);
});

it('throws on an unknown provider', function () {
    expect(fn () => app(PaymentProviderManager::class)->driver('nope'))
        ->toThrow(InvalidArgumentException::class);
});

it('activates immediately under the manual provider', function () {
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)->post(route('billing.checkout.store'), ['plan' => 'growth', 'interval' => 'annual']);

    $sub = $org->activeSubscription();
    expect($sub->status)->toBe('active')
        ->and($sub->interval)->toBe('annual')
        ->and($sub->provider)->toBe('manual')
        ->and($sub->provider_id)->toStartWith('manual_');
});
