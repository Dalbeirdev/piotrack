<?php

namespace App\Content;

/**
 * Engagement snapshot for a social post (ADR-0007).
 */
final class SocialMetrics
{
    public function __construct(
        public int $impressions,
        public int $likes,
        public int $comments,
        public int $shares,
    ) {}
}
