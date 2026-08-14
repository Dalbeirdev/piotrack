<?php

namespace App\Ai;

use App\Ai\Contracts\AiProvider;
use App\Ai\Providers\AnthropicProvider;
use App\Ai\Providers\FixtureAiProvider;
use App\Ai\Providers\OpenAiProvider;

/**
 * Resolves the configured language-model driver (ADR-0008). Defaults to the
 * tested fixture driver; `openai`/`anthropic` select the live (untested) drivers.
 */
class AiProviderManager
{
    public function driver(): AiProvider
    {
        return match ((string) config('ai.driver', 'fixture')) {
            'openai' => new OpenAiProvider,
            'anthropic' => new AnthropicProvider,
            default => new FixtureAiProvider,
        };
    }

    /**
     * Whether the active driver is a real language model. The UI uses this to
     * state plainly when output comes from the fixture driver.
     */
    public function isLive(): bool
    {
        return $this->driver()->name() !== 'fixture';
    }
}
