<?php

namespace App\Analytics\Contracts;

/**
 * Call-tracking data source (CALL). The `fixture` driver is the tested default;
 * the live CallRail/Twilio driver is real but untested here (no credentials).
 * Recording/transcription/AI-summaries are provider-only and remain Planned.
 */
interface CallProvider
{
    /**
     * Provision a dynamic tracking number for a marketing source/campaign.
     *
     * @return array{phone_number: string, provider_id: string}
     */
    public function provisionNumber(string $source, ?string $campaign): array;
}
