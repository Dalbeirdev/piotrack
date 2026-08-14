<?php

namespace App\Content\Contracts;

use App\Content\SocialMetrics;
use App\Models\SocialPost;

/**
 * Social publishing + engagement (ADR-0007). The `fixture` driver is the tested
 * default; live channel drivers are real but untested here (no credentials).
 */
interface SocialProvider
{
    /** Publish the post, returning its platform id (null when unconfigured). */
    public function publish(SocialPost $post): ?string;

    public function metrics(SocialPost $post): SocialMetrics;
}
