<?php

namespace App\Services\Seo;

use App\Models\AiVisibilityCheck;
use App\Seo\Contracts\AiSearchProvider;
use App\Support\AuditLogger;

/**
 * Records AI-engine visibility for a prompt via the configured AiSearchProvider
 * (AEO-018, GEO-001…010). Captures mention/position/cited sources/competitors/
 * share-of-answer as a snapshot for trend + share reporting.
 */
class AiVisibilityService
{
    public function __construct(
        private AiSearchProvider $provider,
        private AuditLogger $audit,
    ) {}

    public function check(string $prompt, string $brand, string $engine = 'chatgpt'): AiVisibilityCheck
    {
        $result = $this->provider->query($prompt, $brand);

        $check = AiVisibilityCheck::create([
            'prompt' => $prompt,
            'engine' => $engine,
            'brand' => $brand,
            'mentioned' => $result->mentioned,
            'position' => $result->position,
            'cited_sources' => $result->citedSources,
            'competitors' => $result->competitors,
            'share_of_answer' => $result->shareOfAnswer,
            'checked_at' => now(),
        ]);

        $this->audit->log('seo.ai.checked', context: ['prompt' => $prompt, 'engine' => $engine, 'mentioned' => $result->mentioned], resourceType: 'ai_visibility_check', resourceId: (string) $check->id, organizationId: $check->organization_id);

        return $check;
    }
}
