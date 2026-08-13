<?php

namespace App\Messaging\Contracts;

use App\Messaging\SentResult;
use App\Messaging\SmsMessage;

/**
 * Transport for a single SMS (ADR-0004). The `log` driver is the tested default;
 * `twilio` is real but untested here (no credentials).
 */
interface SmsProvider
{
    public function send(SmsMessage $message): SentResult;
}
