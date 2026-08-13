<?php

namespace App\Notifications;

class LeadCapturedNotification extends PlatformNotification
{
    public function __construct(
        private string $contactName,
        private string $formName,
    ) {}

    public function category(): string
    {
        return 'leads';
    }

    public function title(): string
    {
        return 'New lead captured';
    }

    public function body(): string
    {
        return "{$this->contactName} submitted the \"{$this->formName}\" form.";
    }

    public function url(): ?string
    {
        return '/marketing/forms';
    }
}
