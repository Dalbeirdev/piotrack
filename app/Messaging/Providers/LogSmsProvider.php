<?php

namespace App\Messaging\Providers;

use App\Messaging\Contracts\SmsProvider;
use App\Messaging\SentResult;
use App\Messaging\SmsMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The default, fully-tested SMS driver (ADR-0004). Records the message to the
 * log and returns accepted; a recipient beginning with {@see self::FAIL_PREFIX}
 * returns a failure for the partial-failure test path.
 */
class LogSmsProvider implements SmsProvider
{
    public const FAIL_PREFIX = '+0000';

    public function send(SmsMessage $message): SentResult
    {
        if (str_starts_with($message->toPhone, self::FAIL_PREFIX)) {
            return SentResult::failed('Simulated SMS failure.');
        }

        Log::info('[marketing.sms] sent', [
            'to' => $message->toPhone,
            'chars' => mb_strlen($message->body),
        ]);

        return SentResult::accepted('log-'.Str::uuid()->toString());
    }
}
