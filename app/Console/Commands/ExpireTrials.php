<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * Closes BILL-011: trials that lapse without conversion become expired.
 * (Hosted providers auto-convert via their own webhooks; the manual/offline
 * provider has no card on file, so a lapsed trial expires.)
 */
class ExpireTrials extends Command
{
    protected $signature = 'subscriptions:expire-trials';

    protected $description = 'Expire trials that have passed their trial end date';

    public function handle(SubscriptionService $subscriptions): int
    {
        $count = 0;

        Subscription::query()
            ->where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->with('organization', 'plan')
            ->each(function (Subscription $subscription) use ($subscriptions, &$count) {
                $subscriptions->expire($subscription);
                $count++;
            });

        $this->components->info("Expired {$count} trial subscription(s).");

        return self::SUCCESS;
    }
}
