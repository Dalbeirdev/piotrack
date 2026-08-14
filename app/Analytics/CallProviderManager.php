<?php

namespace App\Analytics;

use App\Analytics\Contracts\CallProvider;
use App\Analytics\Providers\CallRailProvider;
use App\Analytics\Providers\FixtureCallProvider;

/**
 * Resolves the configured call-tracking driver. Defaults to the tested fixture
 * driver; `callrail` selects the live (untested) driver.
 */
class CallProviderManager
{
    public function driver(): CallProvider
    {
        return (string) config('analytics.calls_driver', 'fixture') === 'callrail'
            ? new CallRailProvider
            : new FixtureCallProvider;
    }
}
