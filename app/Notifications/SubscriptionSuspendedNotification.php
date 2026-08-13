<?php

namespace App\Notifications;

class SubscriptionSuspendedNotification extends PlatformNotification
{
    public function __construct(private string $organizationName) {}

    public function category(): string
    {
        return 'billing';
    }

    public function title(): string
    {
        return 'Subscription suspended';
    }

    public function body(): string
    {
        return "The subscription for {$this->organizationName} has been suspended after an unpaid balance. Reactivate any time from billing.";
    }

    public function url(): ?string
    {
        return '/billing';
    }
}
