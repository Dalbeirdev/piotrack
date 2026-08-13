<?php

namespace App\Messaging\Providers;

use App\Messaging\Contracts\MailProvider;
use App\Messaging\EmailMessage;
use App\Messaging\SentResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The default, fully-tested email driver: records the rendered message to the
 * log and returns an accepted result. The entire pipeline (recipient
 * resolution, suppression, personalization, tracking rows, analytics) runs for
 * real against it — only the transport is simulated (ADR-0004).
 *
 * A recipient address beginning with the {@see self::FAIL_PREFIX} sentinel
 * returns a failed result, so the partial-failure path is provable in tests
 * without a real provider.
 */
class LogMailProvider implements MailProvider
{
    public const FAIL_PREFIX = 'bounce+';

    public function send(EmailMessage $message): SentResult
    {
        if (str_starts_with($message->toEmail, self::FAIL_PREFIX)) {
            return SentResult::failed('Simulated hard bounce.');
        }

        Log::info('[marketing.mail] sent', [
            'to' => $message->toEmail,
            'subject' => $message->subject,
        ]);

        return SentResult::accepted('log-'.Str::uuid()->toString());
    }
}
