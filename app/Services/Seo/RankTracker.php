<?php

namespace App\Services\Seo;

use App\Models\Keyword;
use App\Models\KeywordRanking;
use App\Seo\Contracts\RankProvider;
use App\Seo\SeoProviderManager;
use App\Support\AuditLogger;

/**
 * Records SERP positions for keywords via the configured RankProvider (KSEO-016/
 * 017/018/019). Keeps rank history + the keyword's current position; competitor
 * checks record a separate row. Page-one/top-three are derived from position.
 */
class RankTracker
{
    public function __construct(
        private RankProvider $provider,
        private AuditLogger $audit,
        private SeoProviderManager $providers,
    ) {}

    public function check(Keyword $keyword, string $domain, ?string $location = null, string $engine = 'google'): KeywordRanking
    {
        $result = $this->provider->rank($keyword->phrase, $domain, $location, $engine);

        $ranking = KeywordRanking::create([
            'keyword_id' => $keyword->id,
            'engine' => $engine,
            'location' => $location,
            'position' => $result->position,
            'url' => $result->url,
            // Which driver produced this: a fixture position is a hash, not a
            // ranking, and history must stay self-describing once a real
            // provider is connected.
            'provider' => $this->providers->rankProviderName(),
            'checked_at' => now(),
        ]);

        $keyword->update(['current_position' => $result->position]);

        $this->audit->log('seo.rank.checked', context: ['keyword' => $keyword->phrase, 'position' => $result->position, 'provider' => $this->providers->rankProviderName()], resourceType: 'keyword', resourceId: (string) $keyword->id, organizationId: $keyword->organization_id);

        return $ranking;
    }

    public function checkCompetitor(Keyword $keyword, string $competitorDomain, ?string $location = null, string $engine = 'google'): KeywordRanking
    {
        $result = $this->provider->rank($keyword->phrase, $competitorDomain, $location, $engine);

        return KeywordRanking::create([
            'keyword_id' => $keyword->id,
            'engine' => $engine,
            'location' => $location,
            'position' => $result->position,
            'url' => $result->url,
            'provider' => $this->providers->rankProviderName(),
            'is_competitor' => true,
            'competitor_domain' => $competitorDomain,
            'checked_at' => now(),
        ]);
    }

    public static function isPageOne(?int $position): bool
    {
        return $position !== null && $position <= 10;
    }

    public static function isTopThree(?int $position): bool
    {
        return $position !== null && $position <= 3;
    }
}
