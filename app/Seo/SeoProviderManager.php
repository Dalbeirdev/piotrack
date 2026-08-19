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

    /** The configured rank driver's name, recorded against every position. */
    public function rankProviderName(): string
    {
        return (string) config('seo.rank_provider', 'fixture');
    }

    /**
     * Whether positions come from a real SERP lookup.
     *
     * The fixture driver derives a position from a hash of the inputs, which is
     * useful for exercising the pipeline and useless as a ranking. Mirrors
     * AiProviderManager::isLive() so the UI can say so plainly rather than
     * presenting a hash as a search result.
     */
    public function isRankLive(): bool
    {
        return $this->rankProviderName() !== 'fixture';
    }

    /** The configured AI-search driver's name, recorded against every check. */
    public function aiProviderName(): string
    {
        return (string) config('seo.ai_provider', 'fixture');
    }

    /**
     * Same question for the AI-search driver behind AI visibility, and with a
     * sharper edge: the fixture driver invents competitor domains and citations,
     * which must never be read as findings about a real market.
     */
    public function isAiLive(): bool
    {
        return $this->aiProviderName() !== 'fixture';
    }
}
