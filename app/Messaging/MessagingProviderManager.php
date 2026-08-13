<?php

namespace App\Messaging;

use App\Messaging\Contracts\MailProvider;
use App\Messaging\Contracts\SmsProvider;
use App\Messaging\Providers\LogMailProvider;
use App\Messaging\Providers\LogSmsProvider;
use App\Messaging\Providers\SmtpMailProvider;
use App\Messaging\Providers\TwilioSmsProvider;
use InvalidArgumentException;

/**
 * Resolves the active email/SMS drivers from config (MARKETING_MAIL_PROVIDER /
 * MARKETING_SMS_PROVIDER). Bound so that type-hinting MailProvider / SmsProvider
 * yields the configured driver (mirrors PaymentProviderManager).
 */
class MessagingProviderManager
{
    public function mail(?string $name = null): MailProvider
    {
        $name ??= (string) config('marketing.mail_provider', 'log');

        return match ($name) {
            'log' => new LogMailProvider,
            'smtp' => new SmtpMailProvider,
            default => throw new InvalidArgumentException("Unknown mail provider [{$name}]."),
        };
    }

    public function sms(?string $name = null): SmsProvider
    {
        $name ??= (string) config('marketing.sms_provider', 'log');

        return match ($name) {
            'log' => new LogSmsProvider,
            'twilio' => new TwilioSmsProvider,
            default => throw new InvalidArgumentException("Unknown SMS provider [{$name}]."),
        };
    }
}
