<?php

namespace App\Services\Content;

use App\Content\Contracts\SocialProvider;
use App\Jobs\PublishSocialPost;
use App\Models\SocialPost;
use App\Support\AuditLogger;
use Illuminate\Support\Carbon;

/**
 * Social scheduling + publishing + engagement (SOC). Publishing goes through the
 * SocialProvider (fixture in dev/tests; live channel drivers untested).
 */
class SocialService
{
    public function __construct(
        private SocialProvider $provider,
        private AuditLogger $audit,
    ) {}

    public function schedule(SocialPost $post, Carbon $when): SocialPost
    {
        $post->update(['status' => 'scheduled', 'scheduled_at' => $when]);
        $this->audit->log('content.social.scheduled', context: ['channel' => $post->channel], resourceType: 'social_post', resourceId: (string) $post->id, organizationId: $post->organization_id);

        return $post;
    }

    public function publish(SocialPost $post): SocialPost
    {
        if ($post->isPublished()) {
            return $post;
        }

        $externalId = $this->provider->publish($post);
        $metrics = $this->provider->metrics($post);

        $post->update([
            'status' => 'published',
            'published_at' => now(),
            'external_id' => $externalId,
            'impressions' => $metrics->impressions,
            'likes' => $metrics->likes,
            'comments' => $metrics->comments,
            'shares' => $metrics->shares,
        ]);

        $this->audit->log('content.social.published', context: ['channel' => $post->channel], resourceType: 'social_post', resourceId: (string) $post->id, organizationId: $post->organization_id);

        return $post;
    }

    public function refreshMetrics(SocialPost $post): SocialPost
    {
        $metrics = $this->provider->metrics($post);

        $post->update([
            'impressions' => $metrics->impressions,
            'likes' => $metrics->likes,
            'comments' => $metrics->comments,
            'shares' => $metrics->shares,
        ]);

        return $post;
    }

    /**
     * Dispatch a publish job for every post whose scheduled time is due. Runs
     * across tenants in the console; each job carries its own organization.
     */
    public function dispatchDue(): int
    {
        $due = SocialPost::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $post) {
            PublishSocialPost::dispatch($post->id, $post->organization_id);
        }

        return $due->count();
    }
}
