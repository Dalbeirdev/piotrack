<?php

namespace App\Ai\Providers;

use App\Ai\AiCompletion;
use App\Ai\Contracts\AiProvider;
use App\Ai\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Live Anthropic driver (real, untested — requires an API key). Transient
 * failures (timeout, 429, 5xx) are flagged so the gateway can retry them.
 */
class AnthropicProvider implements AiProvider
{
    public function complete(string $prompt, ?string $system = null): AiCompletion
    {
        $key = (string) config('ai.anthropic.api_key', '');
        if ($key === '') {
            throw new AiProviderException('Anthropic is not configured.');
        }

        $payload = [
            'model' => $this->model(),
            'max_tokens' => (int) config('ai.max_tokens', 1024),
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];
        if ($system !== null && $system !== '') {
            $payload['system'] = $system;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => (string) config('ai.anthropic.version', '2023-06-01'),
            ])->timeout((int) config('ai.timeout', 30))
                ->post('https://api.anthropic.com/v1/messages', $payload);
        } catch (ConnectionException $e) {
            throw new AiProviderException('Anthropic connection failed: '.$e->getMessage(), transient: true);
        }

        if ($response->failed()) {
            $status = $response->status();
            throw new AiProviderException(
                "Anthropic request failed with status {$status}.",
                transient: $status === 429 || $status >= 500,
            );
        }

        $body = $response->json();

        return new AiCompletion(
            text: (string) ($body['content'][0]['text'] ?? ''),
            promptTokens: (int) ($body['usage']['input_tokens'] ?? 0),
            completionTokens: (int) ($body['usage']['output_tokens'] ?? 0),
            model: (string) ($body['model'] ?? $this->model()),
        );
    }

    public function name(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return (string) config('ai.anthropic.model', 'claude-sonnet-5');
    }
}
