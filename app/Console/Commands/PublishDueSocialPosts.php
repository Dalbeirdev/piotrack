<?php

namespace App\Console\Commands;

use App\Services\Content\SocialService;
use Illuminate\Console\Command;

/**
 * Publishes social posts whose scheduled time has arrived (SOC-010). Runs across
 * all tenants; each dispatched job carries its own organization id.
 */
class PublishDueSocialPosts extends Command
{
    protected $signature = 'content:publish-due-posts';

    protected $description = 'Publish scheduled social posts that are due';

    public function handle(SocialService $social): int
    {
        $dispatched = $social->dispatchDue();

        $this->components->info("Dispatched {$dispatched} social post(s).");

        return self::SUCCESS;
    }
}
