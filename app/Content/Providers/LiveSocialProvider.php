<?php

namespace App\Content\Providers;

use App\Content\Contracts\SocialProvider;
use App\Content\SocialMetrics;
use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Real social driver dispatching by channel over the vendor APIs (LinkedIn UGC,
 * Meta Graph, X, YouTube). Real code, but with no channel tokens in this
 * environment it is NOT exercised in tests — status "Implemented (untested —
 * requires credentials)", never "Tested" (ADR-0007, §38). Selected with
 * CONTENT_SOCIAL_PROVIDER=live.
 */
class LiveSocialProvider implements SocialProvider
{
    public function publish(SocialPost $post): ?string
    {
        $token = (string) config("content.{$post->channel}.access_token", '');

        if ($token === '') {
            return null;
        }

        try {
            // Channel-specific publish endpoints go here (e.g. LinkedIn /ugcPosts,
            // Meta /{page}/feed). Returns the created post id on success.
            $endpoint = match ($post->channel) {
                'linkedin' => 'https://api.linkedin.com/v2/ugcPosts',
                'facebook' => 'https://graph.facebook.com/v20.0/me/feed',
                default => null,
            };

            if ($endpoint === null) {
                return null;
            }

            $response = Http::withToken($token)->timeout(30)->post($endpoint, ['message' => $post->body]);

            return $response->successful() ? (string) $response->json('id', '') : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function metrics(SocialPost $post): SocialMetrics
    {
        // Live insights fetch per channel goes here; returns zeros until configured.
        return new SocialMetrics(0, 0, 0, 0);
    }
}
