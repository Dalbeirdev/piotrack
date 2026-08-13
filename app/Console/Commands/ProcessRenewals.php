<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * Closes BILL-012: active subscriptions past their period end are renewed
 * (new period + invoice), unless a cancellation was scheduled — in which case
 * the subscription ends now instead of renewing.
 */
class ProcessRenewals extends Command
{
    protected $signature = 'subscriptions:process-renewals';

    protected $description = 'Renew active subscriptions whose billing period has ended';

    public function handle(SubscriptionService $subscriptions): int
    {
        $renewed = 0;
        $ended = 0;

        Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->with('organization', 'plan')
            ->each(function (Subscription $subscription) use ($subscriptions, &$renewed, &$ended) {
                if ($subscription->cancel_at_period_end) {
                    $subscriptions->cancel($subscription, immediately: true);
                    $ended++;
                } else {
                    $subscriptions->renew($subscription);
                    $renewed++;
                }
            });

        $this->components->info("Renewed {$renewed} and ended {$ended} subscription(s).");

        return self::SUCCESS;
    }
}
