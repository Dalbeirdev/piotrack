<?php

namespace App\Seo\Contracts;

use App\Seo\AiVisibilityResult;

/**
 * AI-engine visibility lookup (ADR-0005). The `fixture` driver is the tested
 * default; `openai`/`perplexity` are real but untested here (no credentials).
 */
interface AiSearchProvider
{
    public function query(string $prompt, string $brand): AiVisibilityResult;
}
