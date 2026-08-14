<?php

namespace App\Seo;

/**
 * A single SERP position lookup (ADR-0005). `position` is null when the domain
 * does not rank in the checked window.
 */
final class RankResult
{
    public function __construct(
        public ?int $position,
        public ?string $url = null,
    ) {}
}
