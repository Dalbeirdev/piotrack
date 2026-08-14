<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\SocialPost;
use App\Services\Content\SocialService;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Publishes a scheduled social post off the request cycle. Re-establishes tenant
 * context; the service is a no-op if the post is already published, so a retry
 * does not double-publish.
 */
class PublishSocialPost implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $postId, public int $organizationId) {}

    public function handle(SocialService $social, CurrentOrganization $current): void
    {
        $organization = Organization::find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $current->set($organization);

        $post = SocialPost::find($this->postId);

        if ($post === null) {
            return;
        }

        $social->publish($post);
    }
}
