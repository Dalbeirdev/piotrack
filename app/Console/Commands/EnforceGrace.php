<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * Closes BILL-016/017: a past-due subscription whose grace window has elapsed
 * is suspended.
 */
class EnforceGrace extends Command
{
    protected $signature = 'subscriptions:enforce-grace';

    protected $description = 'Suspend past-due subscriptions whose grace period has elapsed';

    public function handle(SubscriptionService $subscriptions): int
    {
        $count = 0;

        Subscription::query()
            ->where('status', 'past_due')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->with('organization', 'plan')
            ->each(function (Subscription $subscription) use ($subscriptions, &$count) {
                $subscriptions->suspend($subscription);
                $count++;
            });

        $this->components->info("Suspended {$count} past-due subscription(s).");

        return self::SUCCESS;
    }
}
