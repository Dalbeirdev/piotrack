<?php

namespace App\Services;

use App\Billing\Dto\WebhookEvent;
use App\Models\BillingEvent;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Provider-agnostic webhook processing (BILL-019): every verified event is
 * persisted keyed by (provider, provider_event_id) so processing is idempotent
 * and retry-safe, then dispatched to a handler. Unknown event types are stored
 * and acknowledged (no-op).
 */
class BillingWebhookProcessor
{
    public function __construct(private SubscriptionService $subscriptions) {}

    /**
     * @return string one of: processed | duplicate | ignored | failed
     */
    public function handle(string $provider, WebhookEvent $event): string
    {
        // Idempotency: a repeated event id is a no-op.
        $record = BillingEvent::firstOrNew([
            'provider' => $provider,
            'provider_event_id' => $event->id,
        ]);

        if ($record->exists && $record->status === 'processed') {
            return 'duplicate';
        }

        $record->fill(['type' => $event->type, 'payload' => $event->payload, 'status' => 'received'])->save();

        try {
            $outcome = DB::transaction(fn () => $this->dispatch($event));

            $record->forceFill(['status' => 'processed', 'processed_at' => now(), 'error' => null])->save();

            return $outcome;
        } catch (Throwable $e) {
            $record->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();

            return 'failed';
        }
    }

    private function dispatch(WebhookEvent $event): string
    {
        return match ($event->type) {
            'subscription.activated' => $this->onSubscription($event, fn (Subscription $s) => $this->subscriptions->activate($s)),
            'subscription.payment_failed', 'invoice.payment_failed' => $this->onSubscription($event, fn (Subscription $s) => $this->subscriptions->markPastDue($s)),
            'subscription.suspended' => $this->onSubscription($event, fn (Subscription $s) => $this->subscriptions->suspend($s)),
            'subscription.canceled' => $this->onSubscription($event, fn (Subscription $s) => $this->subscriptions->cancel($s, immediately: true)),
            'invoice.paid' => $this->onInvoicePaid($event),
            default => 'ignored',
        };
    }

    /**
     * @param  callable(Subscription): void  $handler
     */
    private function onSubscription(WebhookEvent $event, callable $handler): string
    {
        $subscription = $this->resolveSubscription($event);

        if ($subscription === null) {
            return 'ignored';
        }

        $handler($subscription);

        return 'processed';
    }

    private function onInvoicePaid(WebhookEvent $event): string
    {
        $invoiceId = $event->payload['invoice_id'] ?? null;
        $invoice = $invoiceId !== null ? Invoice::find($invoiceId) : null;

        if ($invoice === null || $invoice->status === 'paid') {
            return 'ignored';
        }

        $invoice->forceFill(['status' => 'paid', 'amount_paid' => $invoice->total, 'paid_at' => now()])->save();

        return 'processed';
    }

    private function resolveSubscription(WebhookEvent $event): ?Subscription
    {
        if (isset($event->payload['subscription_id'])) {
            return Subscription::find($event->payload['subscription_id']);
        }

        if (isset($event->payload['provider_id'])) {
            return Subscription::where('provider_id', $event->payload['provider_id'])->first();
        }

        return null;
    }
}
