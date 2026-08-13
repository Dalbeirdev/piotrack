<?php

namespace App\Messaging;

/**
 * The outcome of a single send through a MailProvider/SmsProvider. The dispatch
 * pipeline records `accepted` + `messageId` on success and `error` on failure;
 * a failed result never throws — it lets a campaign continue past one bad
 * recipient (partial failure).
 */
final class SentResult
{
    private function __construct(
        public bool $accepted,
        public ?string $messageId = null,
        public ?string $error = null,
    ) {}

    public static function accepted(string $messageId): self
    {
        return new self(true, $messageId);
    }

    public static function failed(string $error): self
    {
        return new self(false, null, $error);
    }
}
