<?php

namespace App\Notifications;

class MemberJoinedNotification extends PlatformNotification
{
    public function __construct(
        private string $memberEmail,
        private string $organizationName,
    ) {}

    public function category(): string
    {
        return 'members';
    }

    public function title(): string
    {
        return 'New member joined';
    }

    public function body(): string
    {
        return "{$this->memberEmail} accepted their invitation to {$this->organizationName}.";
    }

    public function url(): ?string
    {
        return '/settings/members';
    }
}
