<?php

namespace App\Ai\Exceptions;

use RuntimeException;

/**
 * A provider-side failure. `$transient` marks failures worth retrying (timeouts,
 * 429s, 5xx) as opposed to permanent ones (bad request, auth, content refusal).
 */
class AiProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $transient = false)
    {
        parent::__construct($message);
    }
}
