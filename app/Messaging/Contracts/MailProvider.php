<?php

namespace App\Messaging\Contracts;

use App\Messaging\EmailMessage;
use App\Messaging\SentResult;

/**
 * Transport for a single email (ADR-0004). Implementations must not throw on a
 * delivery failure — return SentResult::failed() so the caller can record it and
 * continue. The `log` driver is the tested default; `smtp` is real but untested
 * here (no credentials).
 */
interface MailProvider
{
    public function send(EmailMessage $message): SentResult;
}
