<?php

namespace App\Billing\Dto;

/**
 * Outcome of provisioning a subscription at a provider.
 * - immediate: activate now (manual/offline billing).
 * - redirectUrl: send the customer to hosted checkout (e.g. Stripe); activation
 *   completes via webhook.
 */
readonly class ProviderResult
{
    public function __construct(
        public bool $immediate,
        public ?string $providerId = null,
        public ?string $redirectUrl = null,
    ) {}

    public static function immediate(string $providerId): self
    {
        return new self(immediate: true, providerId: $providerId);
    }

    public static function redirect(string $url, ?string $providerId = null): self
    {
        return new self(immediate: false, providerId: $providerId, redirectUrl: $url);
    }
}
