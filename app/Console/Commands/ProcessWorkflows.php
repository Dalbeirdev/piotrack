<?php

namespace App\Console\Commands;

use App\Services\Marketing\WorkflowEngine;
use Illuminate\Console\Command;

/**
 * Dispatches a step job for every workflow enrollment whose next run is due
 * (AUTO-009…017 drip delays). Runs across all tenants — enrollment queries have
 * no tenant scope in the console, and each dispatched job carries its own org.
 */
class ProcessWorkflows extends Command
{
    protected $signature = 'marketing:process-workflows';

    protected $description = 'Advance due marketing workflow enrollments';

    public function handle(WorkflowEngine $engine): int
    {
        $dispatched = $engine->dispatchDue();

        $this->components->info("Dispatched {$dispatched} workflow step(s).");

        return self::SUCCESS;
    }
}
