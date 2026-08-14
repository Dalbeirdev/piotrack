<?php

namespace App\Ai\Providers;

use App\Ai\AiCompletion;
use App\Ai\Contracts\AiProvider;
use App\Ai\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Live OpenAI driver (real, untested — requires an API key). Transient failures
 * (timeout, 429, 5xx) are flagged so the gateway can retry them.
 */
class OpenAiProvider implements AiProvider
{
    public function complete(string $prompt, ?string $system = null): AiCompletion
    {
        $key = (string) config('ai.openai.api_key', '');
        if ($key === '') {
            throw new AiProviderException('OpenAI is not configured.');
        }

        $messages = [];
        if ($system !== null && $system !== '') {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        try {
            $response = Http::withToken($key)
                ->timeout((int) config('ai.timeout', 30))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->model(),
                    'messages' => $messages,
                ]);
        } catch (ConnectionException $e) {
            throw new AiProviderException('OpenAI connection failed: '.$e->getMessage(), transient: true);
        }

        if ($response->failed()) {
            $status = $response->status();
            throw new AiProviderException(
                "OpenAI request failed with status {$status}.",
                transient: $status === 429 || $status >= 500,
            );
        }

        $body = $response->json();

        return new AiCompletion(
            text: (string) ($body['choices'][0]['message']['content'] ?? ''),
            promptTokens: (int) ($body['usage']['prompt_tokens'] ?? 0),
            completionTokens: (int) ($body['usage']['completion_tokens'] ?? 0),
            model: (string) ($body['model'] ?? $this->model()),
        );
    }

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return (string) config('ai.openai.model', 'gpt-4o-mini');
    }
}
