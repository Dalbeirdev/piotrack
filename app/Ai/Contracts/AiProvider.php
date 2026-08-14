<?php

namespace App\Ai\Contracts;

use App\Ai\AiCompletion;
use App\Ai\Exceptions\AiProviderException;
use App\Services\Ai\AiGateway;

/**
 * Language-model driver (ADR-0008). The `fixture` driver is the tested default;
 * `openai`/`anthropic` are real but untested here (no credentials).
 *
 * Feature code never depends on this directly — every call goes through
 * {@see AiGateway}, which owns prompts, limits, cost and audit.
 */
interface AiProvider
{
    /**
     * @throws AiProviderException on a provider failure
     */
    public function complete(string $prompt, ?string $system = null): AiCompletion;

    public function name(): string;

    public function model(): string;
}
