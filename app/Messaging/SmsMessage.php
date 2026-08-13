<?php

namespace App\Messaging;

/**
 * A provider-agnostic SMS to send.
 */
final class SmsMessage
{
    public function __construct(
        public string $toPhone,
        public string $body,
    ) {}
}
