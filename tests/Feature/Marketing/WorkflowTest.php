<?php

use App\Models\Activity;
use App\Models\Contact;
use App\Models\MarketingList;
use App\Models\Organization;
use App\Models\OutboundMessage;
use App\Models\Workflow;
use App\Models\WorkflowEnrollment;
use App\Services\Marketing\MarketingTrigger;
use App\Services\Marketing\WorkflowEngine;
use App\Support\CurrentOrganization;

/**
 * Build an active workflow with the given steps in the org.
 *
 * @param  list<array{action_type: string, action_config?: array<string, mixed>, delay_minutes?: int}>  $steps
 */
function makeWorkflow(Organization $org, string $trigger, array $steps, string $status = 'active'): Workflow
{
    app(CurrentOrganization::class)->set($org);
    $workflow = Workflow::create(['name' => 'WF', 'trigger_type' => $trigger, 'status' => $status]);
    foreach ($steps as $i => $step) {
        $workflow->steps()->create([
            'position' => $i,
            'action_type' => $step['action_type'],
            'action_config' => $step['action_config'] ?? [],
            'delay_minutes' => $step['delay_minutes'] ?? 0,
        ]);
    }
    app(CurrentOrganization::class)->forget();

    return $workflow;
}

it('enrolls a contact on a trigger and is idempotent while active', function () {
    [$org] = makeOrganization();
    $workflow = makeWorkflow($org, 'form_submission', [['action_type' => 'notify']]);

    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Trig', 'email' => 't@example.com']);
    $trigger = app(MarketingTrigger::class);

    expect($trigger->fire('form_submission', $contact))->toBe(1);
    expect($trigger->fire('form_submission', $contact))->toBe(0); // already active → no-op
    expect(WorkflowEnrollment::where('workflow_id', $workflow->id)->where('contact_id', $contact->id)->count())->toBe(1);
    app(CurrentOrganization::class)->forget();
});

it('does not enroll into a paused workflow', function () {
    [$org] = makeOrganization();
    makeWorkflow($org, 'form_submission', [['action_type' => 'notify']], status: 'paused');

    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'P', 'email' => 'p@example.com']);
    expect(app(MarketingTrigger::class)->fire('form_submission', $contact))->toBe(0);
    app(CurrentOrganization::class)->forget();
});

it('runs steps in order with delays and completes', function () {
    [$org] = makeOrganization();
    $workflow = makeWorkflow($org, 'form_submission', [
        ['action_type' => 'change_lifecycle', 'action_config' => ['stage' => 'mql']],
        ['action_type' => 'send_email', 'action_config' => ['subject' => 'Hi', 'body' => 'Body'], 'delay_minutes' => 60],
    ]);

    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Flow', 'email' => 'flow@example.com']);
    $engine = app(WorkflowEngine::class);
    $enrollment = $engine->enroll($workflow, $contact);

    // Step 0 runs → lifecycle changes, next step scheduled ~60m out.
    $engine->processEnrollment($enrollment->refresh());
    expect($contact->refresh()->lifecycle_stage)->toBe('mql');
    $enrollment->refresh();
    expect($enrollment->current_position)->toBe(1)
        ->and($enrollment->status)->toBe('active')
        ->and($enrollment->next_run_at->greaterThan(now()->addMinutes(50)))->toBeTrue();

    // Step 1 runs → email sent, enrollment completes.
    $engine->processEnrollment($enrollment);
    expect(OutboundMessage::where('contact_id', $contact->id)->where('status', 'sent')->exists())->toBeTrue();
    expect($enrollment->refresh()->status)->toBe('completed');
    expect($workflow->refresh()->completed_count)->toBe(1);
    app(CurrentOrganization::class)->forget();
});

it('executes each action type', function () {
    [$org] = makeOrganization();

    app(CurrentOrganization::class)->set($org);
    $list = MarketingList::create(['name' => 'L', 'type' => 'static']);
    $contact = Contact::create(['first_name' => 'Act', 'email' => 'act@example.com', 'lead_score' => 10]);
    app(CurrentOrganization::class)->forget();

    $workflow = makeWorkflow($org, 'form_submission', [
        ['action_type' => 'change_score', 'action_config' => ['delta' => 15]],
        ['action_type' => 'add_to_list', 'action_config' => ['list_id' => $list->id]],
        ['action_type' => 'create_task', 'action_config' => ['title' => 'Call them']],
    ]);

    app(CurrentOrganization::class)->set($org);
    $engine = app(WorkflowEngine::class);
    $enrollment = $engine->enroll($workflow, $contact);
    $engine->processEnrollment($enrollment->refresh()); // score
    $engine->processEnrollment($enrollment->refresh()); // add to list
    $engine->processEnrollment($enrollment->refresh()); // task

    expect($contact->refresh()->lead_score)->toBe(25);
    expect($list->refresh()->member_count)->toBe(1);
    expect(Activity::where('subject_type', 'contact')->where('subject_id', $contact->id)->where('type', 'task')->exists())->toBeTrue();
    app(CurrentOrganization::class)->forget();
});
