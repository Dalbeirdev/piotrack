<?php

namespace App\Billing\Dto;

/**
 * A verified, normalized inbound billing event.
 */
readonly class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $payload,
    ) {}
}
