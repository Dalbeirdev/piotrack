<?php

namespace App\Console\Commands;

use Database\Seeders\PlanSeeder;
use Illuminate\Console\Command;

class SyncPlans extends Command
{
    protected $signature = 'billing:sync-plans';

    protected $description = 'Sync the plan catalog (code) into the plans/prices/entitlements tables';

    public function handle(): int
    {
        $this->components->info('Syncing plan catalog…');
        (new PlanSeeder)->setContainer($this->laravel)->run();
        $this->components->info('Plans synced.');

        return self::SUCCESS;
    }
}
