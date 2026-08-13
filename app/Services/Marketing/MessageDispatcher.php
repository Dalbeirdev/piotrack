<?php

namespace App\Services\Marketing;

use App\Marketing\EmailBody;
use App\Marketing\MergeTags;
use App\Messaging\Contracts\MailProvider;
use App\Messaging\Contracts\SmsProvider;
use App\Messaging\EmailMessage;
use App\Messaging\SmsMessage;
use App\Models\Contact;
use App\Models\OutboundMessage;
use Illuminate\Support\Str;

/**
 * Sends a single email/SMS to a contact (used by automation actions). Enforces
 * consent (suppression + per-contact opt-in), renders merge tags + tracking,
 * dispatches via the configured provider, and records an OutboundMessage that
 * the tracking endpoints update. Never throws on a provider failure — the row
 * is marked failed with the error.
 */
class MessageDispatcher
{
    public function __construct(
        private MailProvider $mail,
        private SmsProvider $sms,
        private SuppressionService $suppressions,
    ) {}

    public function sendEmail(Contact $contact, string $subject, string $body, string $source = 'automation', ?int $workflowId = null): OutboundMessage
    {
        $message = OutboundMessage::create([
            'contact_id' => $contact->id,
            'channel' => 'email',
            'address' => (string) $contact->email,
            'subject' => $subject,
            'body' => $body,
            'token' => Str::random(48),
            'status' => 'pending',
            'source' => $source,
            'workflow_id' => $workflowId,
        ]);

        if (! $contact->email_opt_in || $this->suppressions->isSuppressed('email', $contact->email)) {
            $message->update(['status' => 'failed', 'error' => 'suppressed']);

            return $message;
        }

        $html = EmailBody::withTracking(MergeTags::render($body, $contact), $message->token);

        $result = $this->mail->send(new EmailMessage(
            toEmail: (string) $contact->email,
            subject: MergeTags::render($subject, $contact),
            html: $html,
            fromEmail: (string) config('marketing.from.email'),
            fromName: (string) config('marketing.from.name'),
        ));

        $message->update($result->accepted
            ? ['status' => 'sent', 'sent_at' => now(), 'provider_message_id' => $result->messageId]
            : ['status' => 'failed', 'error' => $result->error]);

        return $message;
    }

    public function sendSms(Contact $contact, string $body, string $source = 'automation', ?int $workflowId = null): OutboundMessage
    {
        $message = OutboundMessage::create([
            'contact_id' => $contact->id,
            'channel' => 'sms',
            'address' => (string) $contact->phone,
            'body' => $body,
            'token' => Str::random(48),
            'status' => 'pending',
            'source' => $source,
            'workflow_id' => $workflowId,
        ]);

        if (! $contact->sms_opt_in || $this->suppressions->isSuppressed('sms', $contact->phone)) {
            $message->update(['status' => 'failed', 'error' => 'suppressed']);

            return $message;
        }

        $result = $this->sms->send(new SmsMessage(
            toPhone: (string) $contact->phone,
            body: MergeTags::render($body, $contact),
        ));

        $message->update($result->accepted
            ? ['status' => 'sent', 'sent_at' => now(), 'provider_message_id' => $result->messageId]
            : ['status' => 'failed', 'error' => $result->error]);

        return $message;
    }
}
