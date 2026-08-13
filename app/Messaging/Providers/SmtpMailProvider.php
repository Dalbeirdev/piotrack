<?php

namespace App\Messaging\Providers;

use App\Messaging\Contracts\MailProvider;
use App\Messaging\EmailMessage;
use App\Messaging\SentResult;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Real email driver over Laravel's configured mailer (SMTP/SES/Mailgun/etc.).
 * This is real code, but with no mail credentials in this environment it is
 * NOT exercised in tests — its register status is "Implemented (untested —
 * requires credentials)", never "Tested" (ADR-0004, §38). Selected with
 * MARKETING_MAIL_PROVIDER=smtp.
 */
class SmtpMailProvider implements MailProvider
{
    public function send(EmailMessage $message): SentResult
    {
        try {
            Mail::html($message->html, function (Message $mail) use ($message) {
                $mail->to($message->toEmail)->subject($message->subject);

                if ($message->fromEmail !== null) {
                    $mail->from($message->fromEmail, $message->fromName);
                }

                foreach ($message->headers as $name => $value) {
                    $mail->getHeaders()->addTextHeader($name, $value);
                }
            });

            return SentResult::accepted('smtp-'.Str::uuid()->toString());
        } catch (Throwable $e) {
            return SentResult::failed($e->getMessage());
        }
    }
}
