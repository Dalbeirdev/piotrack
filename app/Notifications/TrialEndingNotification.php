<?php

namespace App\Notifications;

class TrialEndingNotification extends PlatformNotification
{
    public function __construct(private string $organizationName, private int $daysLeft) {}

    public function category(): string
    {
        return 'billing';
    }

    public function title(): string
    {
        return 'Your trial is ending soon';
    }

    public function body(): string
    {
        $days = $this->daysLeft === 1 ? '1 day' : "{$this->daysLeft} days";

        return "The trial for {$this->organizationName} ends in {$days}. Choose a plan to keep your access.";
    }

    public function url(): ?string
    {
        return '/billing/plans';
    }
}
