<?php

namespace App\Notifications;

class PaymentFailedNotification extends PlatformNotification
{
    public function __construct(private string $organizationName) {}

    public function category(): string
    {
        return 'billing';
    }

    public function title(): string
    {
        return 'Payment failed';
    }

    public function body(): string
    {
        return "We couldn't process a payment for {$this->organizationName}. Please update your billing details to avoid interruption.";
    }

    public function url(): ?string
    {
        return '/billing';
    }
}
