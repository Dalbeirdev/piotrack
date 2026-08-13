<?php

namespace App\Services\Marketing;

use App\Models\Suppression;
use App\Support\CurrentOrganization;

/**
 * The central consent gate (ADR-0004). Every send checks this before dispatch,
 * so unsubscribe/opt-out cannot be bypassed by any campaign or workflow.
 */
class SuppressionService
{
    public function __construct(private CurrentOrganization $currentOrganization) {}

    public function isSuppressed(string $channel, ?string $address): bool
    {
        if ($address === null || $address === '') {
            return true;
        }

        return Suppression::where('channel', $channel)->where('address', $address)->exists();
    }

    public function suppress(string $channel, string $address, string $reason, ?int $contactId = null): Suppression
    {
        return Suppression::firstOrCreate(
            [
                'organization_id' => $this->currentOrganization->id(),
                'channel' => $channel,
                'address' => $address,
            ],
            ['reason' => $reason, 'contact_id' => $contactId],
        );
    }
}
