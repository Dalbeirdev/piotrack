<?php

use App\Models\Coupon;

it('applies a percentage coupon to the invoice', function () {
    [$org, $owner] = makeOrganization();
    Coupon::create(['code' => 'SAVE20', 'type' => 'percent', 'value' => 20, 'duration' => 'once', 'is_active' => true]);

    $this->actingAs($owner)
        ->post(route('billing.checkout.store'), ['plan' => 'professional', 'interval' => 'monthly', 'coupon' => 'SAVE20'])
        ->assertRedirect(route('billing.index'));

    $invoice = $org->invoices()->latest('id')->first();
    expect($invoice->subtotal)->toBe(34900)
        ->and($invoice->discount)->toBe(6980) // 20% of 34900
        ->and($invoice->total)->toBe(27920);

    expect(Coupon::firstWhere('code', 'SAVE20')->times_redeemed)->toBe(1);
});

it('applies a fixed-amount coupon', function () {
    [$org, $owner] = makeOrganization();
    Coupon::create(['code' => 'FLAT50', 'type' => 'amount', 'value' => 5000, 'currency' => 'USD', 'duration' => 'once', 'is_active' => true]);

    $this->actingAs($owner)
        ->post(route('billing.checkout.store'), ['plan' => 'growth', 'interval' => 'monthly', 'coupon' => 'FLAT50']);

    $invoice = $org->invoices()->latest('id')->first();
    expect($invoice->discount)->toBe(5000)->and($invoice->total)->toBe(14900 - 5000);
});

it('rejects an invalid coupon at checkout', function () {
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)
        ->post(route('billing.checkout.store'), ['plan' => 'growth', 'interval' => 'monthly', 'coupon' => 'NOPE'])
        ->assertSessionHasErrors('coupon');
});

it('rejects an expired coupon', function () {
    [$org, $owner] = makeOrganization();
    Coupon::create(['code' => 'OLD', 'type' => 'percent', 'value' => 10, 'duration' => 'once', 'is_active' => true, 'expires_at' => now()->subDay()]);

    $this->actingAs($owner)
        ->post(route('billing.checkout.store'), ['plan' => 'growth', 'interval' => 'monthly', 'coupon' => 'OLD'])
        ->assertSessionHasErrors('coupon');
});

it('rejects a coupon that has hit its redemption limit', function () {
    [$org, $owner] = makeOrganization();
    Coupon::create(['code' => 'MAXED', 'type' => 'percent', 'value' => 10, 'duration' => 'once', 'is_active' => true, 'max_redemptions' => 1, 'times_redeemed' => 1]);

    $this->actingAs($owner)
        ->post(route('billing.checkout.store'), ['plan' => 'growth', 'interval' => 'monthly', 'coupon' => 'MAXED'])
        ->assertSessionHasErrors('coupon');
});
