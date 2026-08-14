<?php

namespace App\Seo\Providers;

use App\Seo\AiVisibilityResult;
use App\Seo\Contracts\AiSearchProvider;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Real AI-visibility driver: asks an LLM the prompt and inspects the answer for
 * the brand. Real code, but with no API key here it is NOT exercised in tests —
 * status "Implemented (untested — requires credentials)", never "Tested"
 * (ADR-0005, §38). Selected with SEO_AI_PROVIDER=openai.
 */
class OpenAiSearchProvider implements AiSearchProvider
{
    public function query(string $prompt, string $brand): AiVisibilityResult
    {
        $key = (string) config('seo.openai.key');

        if ($key === '') {
            return new AiVisibilityResult(false, null, [], [], 0);
        }

        try {
            $response = Http::withToken($key)->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => (string) config('seo.openai.model', 'gpt-4o-mini'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            if ($response->failed()) {
                return new AiVisibilityResult(false, null, [], [], 0);
            }

            $answer = (string) $response->json('choices.0.message.content', '');
            $mentioned = mb_stripos($answer, $brand) !== false;

            return new AiVisibilityResult($mentioned, $mentioned ? 1 : null, [], [], $mentioned ? 100 : 0);
        } catch (Throwable) {
            return new AiVisibilityResult(false, null, [], [], 0);
        }
    }
}
