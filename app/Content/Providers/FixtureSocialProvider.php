<?php

namespace App\Content\Providers;

use App\Content\Contracts\SocialProvider;
use App\Content\SocialMetrics;
use App\Models\SocialPost;

/**
 * The default, fully-tested social driver: deterministic engagement derived from
 * a hash of the post. The whole pipeline — scheduling, publish status, metric
 * snapshots — runs for real against it (ADR-0007). Also a legitimate manual mode.
 */
class FixtureSocialProvider implements SocialProvider
{
    public function publish(SocialPost $post): ?string
    {
        return 'fixture-'.$post->channel.'-'.$post->id;
    }

    public function metrics(SocialPost $post): SocialMetrics
    {
        $seed = crc32($post->id.'|'.$post->channel);

        $impressions = 200 + $seed % 5000;
        $likes = (int) round($impressions * (0.01 + ($seed % 50) / 1000));
        $comments = (int) round($likes * 0.1);
        $shares = (int) round($likes * 0.05);

        return new SocialMetrics($impressions, $likes, $comments, $shares);
    }
}
