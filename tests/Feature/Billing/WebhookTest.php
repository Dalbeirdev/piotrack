<?php

use App\Models\BillingEvent;

function postWebhook(array $body, ?string $signature = 'local-manual-secret')
{
    $headers = $signature !== null ? ['X-Billing-Signature' => $signature] : [];

    return test()->postJson('/webhooks/manual', $body, $headers);
}

it('rejects a webhook with a bad signature', function () {
    postWebhook(['id' => 'evt_1', 'type' => 'subscription.suspended', 'data' => []], signature: 'wrong')
        ->assertStatus(400);

    expect(BillingEvent::count())->toBe(0);
});

it('rejects an unknown provider', function () {
    $this->postJson('/webhooks/does-not-exist', ['id' => 'x', 'type' => 'y'], ['X-Billing-Signature' => 'local-manual-secret'])
        ->assertStatus(404);
});

it('processes a payment_failed event into a past-due subscription', function () {
    [$org] = makeOrganization();
    subscribeOrganization($org, 'growth');
    $sub = $org->activeSubscription();

    postWebhook(['id' => 'evt_pf_1', 'type' => 'subscription.payment_failed', 'data' => ['subscription_id' => $sub->id]])
        ->assertOk()
        ->assertJsonPath('status', 'processed');

    expect($sub->refresh()->status)->toBe('past_due');
});

it('is idempotent — a duplicate event is a no-op', function () {
    [$org] = makeOrganization();
    subscribeOrganization($org, 'growth');
    $sub = $org->activeSubscription();

    postWebhook(['id' => 'evt_dupe', 'type' => 'subscription.suspended', 'data' => ['subscription_id' => $sub->id]])->assertOk();
    postWebhook(['id' => 'evt_dupe', 'type' => 'subscription.suspended', 'data' => ['subscription_id' => $sub->id]])
        ->assertOk()
        ->assertJsonPath('status', 'duplicate');

    expect(BillingEvent::where('provider_event_id', 'evt_dupe')->count())->toBe(1);
});

it('suspends a subscription on a suspended event', function () {
    [$org] = makeOrganization();
    subscribeOrganization($org, 'growth');
    $sub = $org->activeSubscription();

    postWebhook(['id' => 'evt_susp', 'type' => 'subscription.suspended', 'data' => ['subscription_id' => $sub->id]])->assertOk();

    expect($sub->refresh()->status)->toBe('suspended');
});

it('acknowledges an unknown event type without failing', function () {
    postWebhook(['id' => 'evt_unknown', 'type' => 'something.else', 'data' => []])
        ->assertOk()
        ->assertJsonPath('status', 'ignored');

    expect(BillingEvent::where('provider_event_id', 'evt_unknown')->where('status', 'processed')->exists())->toBeTrue();
});
