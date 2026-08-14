<?php

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Ticket;
use App\Services\Delivery\ProjectService;
use App\Services\Delivery\TicketService;
use App\Support\CurrentOrganization;

it('staffs the delivery roles on a project', function () {
    [$org, $owner] = deliveryOrganization();
    app(CurrentOrganization::class)->set($org);

    $service = app(ProjectService::class);
    $project = $service->create(['name' => 'Growth engagement']);
    $service->assign($project, $owner, 'strategist');
    $service->assign($project, $owner, 'project_manager');
    $service->assign($project, $owner, 'strategist'); // idempotent

    expect(ProjectMember::where('project_id', $project->id)->count())->toBe(2)
        ->and(fn () => $service->assign($project, $owner, 'astronaut'))->toThrow(RuntimeException::class);
    app(CurrentOrganization::class)->forget();
});

it('tracks sprint and task progress', function () {
    [$org, $owner] = deliveryOrganization();
    app(CurrentOrganization::class)->set($org);
    $service = app(ProjectService::class);

    $project = $service->create(['name' => 'Q3 sprint work']);
    $sprint = $service->startSprint($project, ['name' => 'Sprint 1']);
    $service->addTask($project, ['title' => 'Audit site', 'sprint_id' => $sprint->id, 'status' => 'done']);
    $service->addTask($project, ['title' => 'Fix meta', 'sprint_id' => $sprint->id]);
    $service->addTask($project, ['title' => 'Overdue item', 'due_on' => now()->subWeek()->toDateString()]);

    $progress = $service->progress($project);
    app(CurrentOrganization::class)->forget();

    expect($progress['tasks'])->toBe(3)
        ->and($progress['done'])->toBe(1)
        ->and($progress['overdue'])->toBe(1)
        ->and($progress['completion'])->toBe(33.33);
});

it('runs a deliverable through submit and approval', function () {
    [$org, $owner] = deliveryOrganization();
    app(CurrentOrganization::class)->set($org);
    $service = app(ProjectService::class);

    $project = $service->create(['name' => 'Website refresh']);
    $deliverable = $service->addDeliverable($project, ['title' => 'Homepage copy']);
    expect($deliverable->client_visible)->toBeFalse();

    $service->submitForApproval($deliverable);
    expect($deliverable->refresh()->approval_status)->toBe(Deliverable::APPROVAL_PENDING)
        // Submitting makes it visible — a client cannot approve what they cannot see.
        ->and($deliverable->client_visible)->toBeTrue();

    $approved = $service->approve($deliverable, $owner);
    app(CurrentOrganization::class)->forget();

    expect($approved->approval_status)->toBe(Deliverable::APPROVAL_APPROVED)
        ->and($approved->status)->toBe('delivered')
        ->and($approved->approved_by)->toBe($owner->id)
        ->and($approved->approved_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'projects.deliverable.approved')->exists())->toBeTrue();
});

it('makes rejection recoverable rather than destructive', function () {
    [$org, $owner] = deliveryOrganization();
    app(CurrentOrganization::class)->set($org);
    $service = app(ProjectService::class);

    $project = $service->create(['name' => 'Campaign']);
    $deliverable = $service->submitForApproval($service->addDeliverable($project, ['title' => 'Ad creative']));
    $rejected = $service->reject($deliverable, $owner, 'Wrong brand colours');

    expect($rejected->approval_status)->toBe(Deliverable::APPROVAL_REJECTED)
        // Back to in-progress so the work can be revised — the record survives.
        ->and($rejected->status)->toBe('in_progress')
        ->and($rejected->rejection_reason)->toBe('Wrong brand colours')
        ->and(Deliverable::whereKey($rejected->id)->exists())->toBeTrue();

    // A rejected deliverable is no longer awaiting a decision.
    expect(fn () => $service->approve($rejected, $owner))->toThrow(RuntimeException::class);
    app(CurrentOrganization::class)->forget();
});

it('stops a viewer from approving a deliverable through the route', function () {
    [$org, $owner] = deliveryOrganization();
    $viewer = addMember($org, Role::Viewer);

    app(CurrentOrganization::class)->set($org);
    $service = app(ProjectService::class);
    $project = $service->create(['name' => 'Gated']);
    $deliverable = $service->submitForApproval($service->addDeliverable($project, ['title' => 'Report']));
    app(CurrentOrganization::class)->forget();

    $this->actingAs($viewer)->post(route('projects.deliverables.approve', $deliverable->id))->assertForbidden();
    expect($deliverable->refresh()->approval_status)->toBe(Deliverable::APPROVAL_PENDING);

    $this->actingAs($owner)->post(route('projects.deliverables.approve', $deliverable->id))->assertRedirect();
    expect($deliverable->refresh()->approval_status)->toBe(Deliverable::APPROVAL_APPROVED);
});

it('keeps internal ticket notes out of the client thread', function () {
    [$org, $owner] = deliveryOrganization();
    app(CurrentOrganization::class)->set($org);
    $service = app(TicketService::class);

    $ticket = $service->open(['subject' => 'Site is slow', 'body' => 'Pages take 8s'], $owner);
    $service->reply($ticket, 'We are looking into it.', $owner);
    $service->reply($ticket, 'Their CDN config is wrong, do not tell them yet.', $owner, internal: true);

    $thread = $service->clientThread($ticket);
    app(CurrentOrganization::class)->forget();

    expect($ticket->messages()->count())->toBe(2)
        ->and($thread)->toHaveCount(1)
        ->and($thread[0]['body'])->toBe('We are looking into it.');
});

it('reopens a resolved ticket when someone replies', function () {
    [$org, $owner] = deliveryOrganization();
    app(CurrentOrganization::class)->set($org);
    $service = app(TicketService::class);

    $ticket = $service->open(['subject' => 'Question', 'body' => 'How do I export?'], $owner);
    $service->resolve($ticket);
    expect($ticket->refresh()->status)->toBe('resolved');

    $service->reply($ticket, 'Actually one more thing', $owner);
    app(CurrentOrganization::class)->forget();

    expect($ticket->refresh()->status)->toBe('open')->and($ticket->resolved_at)->toBeNull();
});

it('isolates projects across tenants', function () {
    [, $ownerA] = deliveryOrganization('Tenant A');
    [$orgB] = deliveryOrganization('Tenant B');

    app(CurrentOrganization::class)->set($orgB);
    $projectB = app(ProjectService::class)->create(['name' => 'B project']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($ownerA)->patch(route('projects.update', $projectB->id), ['name' => 'hijacked'])->assertNotFound();
    expect($projectB->refresh()->name)->toBe('B project');

    expect(Ticket::withoutGlobalScope('tenant')->count())->toBe(0);
    expect(Project::withoutGlobalScope('tenant')->count())->toBe(1);
});
