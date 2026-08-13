<?php

namespace App\Notifications;

class WorkflowNotification extends PlatformNotification
{
    public function __construct(
        private string $message,
        private ?string $link = null,
    ) {}

    public function category(): string
    {
        return 'automation';
    }

    public function title(): string
    {
        return 'Workflow notification';
    }

    public function body(): string
    {
        return $this->message;
    }

    public function url(): ?string
    {
        return $this->link;
    }
}
