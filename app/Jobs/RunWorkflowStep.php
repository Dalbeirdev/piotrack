<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\WorkflowEnrollment;
use App\Services\Marketing\WorkflowEngine;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Advances one workflow enrollment by a single step. Re-establishes tenant
 * context from the enrollment's organization. Idempotent on current_position:
 * the engine executes the step at the current position and moves the pointer,
 * so a retry re-runs at most the same step.
 */
class RunWorkflowStep implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $enrollmentId, public int $organizationId) {}

    public function handle(WorkflowEngine $engine, CurrentOrganization $current): void
    {
        $organization = Organization::find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $current->set($organization);

        $enrollment = WorkflowEnrollment::find($this->enrollmentId);

        if ($enrollment === null) {
            return;
        }

        $engine->processEnrollment($enrollment);
    }
}
