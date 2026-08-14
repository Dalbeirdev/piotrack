<?php

namespace App\Analytics\Providers;

use App\Analytics\Contracts\CallProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Live CallRail tracking-number provider (real, untested — requires an API key).
 * Recording/transcription/AI summaries are fetched by CallRail webhooks and are
 * out of scope until credentials + the INTG webhook are wired.
 */
class CallRailProvider implements CallProvider
{
    public function provisionNumber(string $source, ?string $campaign): array
    {
        $key = (string) config('analytics.callrail.api_key', '');
        $account = (string) config('analytics.callrail.account_id', '');
        if ($key === '' || $account === '') {
            throw new RuntimeException('CallRail is not configured.');
        }

        $response = Http::withToken($key)
            ->post("https://api.callrail.com/v3/a/{$account}/trackers.json", [
                'name' => trim($source.' '.((string) $campaign)),
                'type' => 'source',
            ])
            ->throw()
            ->json();

        return [
            'phone_number' => (string) ($response['tracking_numbers'][0] ?? ''),
            'provider_id' => (string) ($response['id'] ?? ''),
        ];
    }
}
