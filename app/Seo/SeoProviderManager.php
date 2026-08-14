<?php

namespace App\Seo;

use App\Seo\Contracts\AiSearchProvider;
use App\Seo\Contracts\RankProvider;
use App\Seo\Providers\FixtureAiSearchProvider;
use App\Seo\Providers\FixtureRankProvider;
use App\Seo\Providers\OpenAiSearchProvider;
use App\Seo\Providers\SerpApiRankProvider;
use InvalidArgumentException;

/**
 * Resolves the active rank / AI-search drivers from config (SEO_RANK_PROVIDER /
 * SEO_AI_PROVIDER). Bound so that type-hinting RankProvider / AiSearchProvider
 * yields the configured driver (mirrors PaymentProviderManager).
 */
class SeoProviderManager
{
    public function rank(?string $name = null): RankProvider
    {
        $name ??= (string) config('seo.rank_provider', 'fixture');

        return match ($name) {
            'fixture' => new FixtureRankProvider,
            'serpapi' => new SerpApiRankProvider,
            default => throw new InvalidArgumentException("Unknown rank provider [{$name}]."),
        };
    }

    public function ai(?string $name = null): AiSearchProvider
    {
        $name ??= (string) config('seo.ai_provider', 'fixture');

        return match ($name) {
            'fixture' => new FixtureAiSearchProvider,
            'openai' => new OpenAiSearchProvider,
            default => throw new InvalidArgumentException("Unknown AI search provider [{$name}]."),
        };
    }
}
