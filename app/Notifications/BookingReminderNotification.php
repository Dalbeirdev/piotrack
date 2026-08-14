<?php

namespace App\Notifications;

class BookingReminderNotification extends PlatformNotification
{
    public function __construct(
        private string $guestName,
        private string $when,
    ) {}

    public function category(): string
    {
        return 'sales';
    }

    public function title(): string
    {
        return 'Upcoming meeting';
    }

    public function body(): string
    {
        return "Reminder: meeting with {$this->guestName} at {$this->when}.";
    }

    public function url(): ?string
    {
        return '/sales/booking';
    }
}
