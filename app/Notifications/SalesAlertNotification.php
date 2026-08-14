<?php

namespace App\Notifications;

class SalesAlertNotification extends PlatformNotification
{
    public function __construct(private string $alertMessage) {}

    public function category(): string
    {
        return 'sales';
    }

    public function title(): string
    {
        return 'Sales alert';
    }

    public function body(): string
    {
        return $this->alertMessage;
    }

    public function url(): ?string
    {
        return '/sales/alerts';
    }
}
