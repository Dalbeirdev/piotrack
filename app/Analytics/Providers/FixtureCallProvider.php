<?php

namespace App\Analytics\Providers;

use App\Analytics\Contracts\CallProvider;

/**
 * Deterministic tracking-number provider for local/dev/test — derives a stable
 * toll-free number from the source/campaign so tests are reproducible.
 */
class FixtureCallProvider implements CallProvider
{
    public function provisionNumber(string $source, ?string $campaign): array
    {
        $seed = substr(md5($source.'|'.((string) $campaign)), 0, 7);
        $digits = str_pad((string) (hexdec($seed) % 10000000), 7, '0', STR_PAD_LEFT);

        return [
            'phone_number' => '+1888'.$digits,
            'provider_id' => 'fix_'.$seed,
        ];
    }
}
