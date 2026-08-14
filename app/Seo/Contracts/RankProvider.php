<?php

namespace App\Seo\Contracts;

use App\Seo\RankResult;

/**
 * SERP rank lookup (ADR-0005). The `fixture` driver is the tested default;
 * `serpapi`/`dataforseo` are real but untested here (no credentials).
 */
interface RankProvider
{
    public function rank(string $keyword, string $domain, ?string $location, string $engine): RankResult;
}
