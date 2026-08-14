<?php

namespace App\Ai;

/**
 * The result of one model call (ADR-0008): the generated text plus the token
 * accounting the gateway needs to attribute cost.
 */
final class AiCompletion
{
    public function __construct(
        public string $text,
        public int $promptTokens,
        public int $completionTokens,
        public string $model,
    ) {}

    public function totalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }
}
