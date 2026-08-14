<?php

namespace App\Ai\Exceptions;

use RuntimeException;

/**
 * The tenant has no AI credits left on its plan. Raised before any provider call
 * is made, so an out-of-credit tenant cannot incur spend (AIPF-004).
 */
class AiCreditsExhaustedException extends RuntimeException
{
    public function __construct(string $message = 'AI credit limit reached for this plan.')
    {
        parent::__construct($message);
    }
}
