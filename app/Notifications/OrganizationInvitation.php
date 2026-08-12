<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitation extends Notification
{
    use Queueable;

    public function __construct(
        private string $organizationName,
        private string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('invitations.show', ['token' => $this->token]);

        return (new MailMessage)
            ->subject("You've been invited to join {$this->organizationName} on ".config('app.name'))
            ->greeting('Hello,')
            ->line("You have been invited to join the {$this->organizationName} organization on ".config('app.name').'.')
            ->action('Accept invitation', $url)
            ->line('This invitation expires in 7 days. If you did not expect it, you can ignore this email.');
    }
}
