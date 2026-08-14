<?php

use App\Authorization\Role;
use App\Models\Deliverable;
use App\Models\Ticket;
use App\Services\Delivery\PortalService;
use App\Services\Delivery\ProjectService;
use App\Services\Delivery\TicketService;
use App\Support\CurrentOrganization;

it('shows the client only deliverables marked visible to them', function () {
    [$org] = deliveryOrganization();
    app(CurrentOrganization::class)->set($org);
    $projects = app(ProjectService::class);

    $project = $projects->create(['name' => 'Engagement']);
    $projects->addDeliverable($project, ['title' => 'Internal working doc']);                       // hidden
    $projects->addDeliverable($project, ['title' => 'Client report', 'client_visible' => true]);    // visible

    $visible = app(PortalService::class)->deliverables();
    app(CurrentOrganization::class)->forget();

    expect($visible)->toHaveCount(1)
        ->and($visible[0]['title'])->toBe('Client report');
});

it('does not reveal that a hidden deliverable exists', function () {
    [$org] = deliveryOrganization();
    $client = addMember($org, Role::Client);

    app(CurrentOrganization::class)->set($org);
    $projects = app(ProjectService::class);
    $project = $projects->create(['name' => 'Engagement']);
    $hidden = $projects->addDeliverable($project, ['title' => 'Internal only']);
    $hidden->update(['approval_status' => Deliverable::APPROVAL_PENDING]);
    app(CurrentOrganization::class)->forget();

    // Not found rather than forbidden: the portal never confirms its existence.
    $this->actingAs($client)->post(route('portal.deliverables.approve', $hidden->id))->assertNotFound();
    expect($hidden->refresh()->approval_status)->toBe(Deliverable::APPROVAL_PENDING);
});

it('lets a client approve a submitted deliverable', function () {
    [$org] = deliveryOrganization();
    $client = addMember($org, Role::Client);

    app(CurrentOrganization::class)->set($org);
    $projects = app(ProjectService::class);
    $deliverable = $projects->submitForApproval(
        $projects->addDeliverable($projects->create(['name' => 'Engagement']), ['title' => 'Homepage design']),
    );
    app(CurrentOrganization::class)->forget();

    $this->actingAs($client)->post(route('portal.deliverables.approve', $deliverable->id))->assertRedirect();

    expect($deliverable->refresh()->approval_status)->toBe(Deliverable::APPROVAL_APPROVED)
        ->and($deliverable->approved_by)->toBe($client->id);
});

it('lets a client send a deliverable back with feedback', function () {
    [$org] = deliveryOrganization();
    $client = addMember($org, Role::Client);

    app(CurrentOrganization::class)->set($org);
    $projects = app(ProjectService::class);
    $deliverable = $projects->submitForApproval(
        $projects->addDeliverable($projects->create(['name' => 'Engagement']), ['title' => 'Ad set']),
    );
    app(CurrentOrganization::class)->forget();

    $this->actingAs($client)
        ->post(route('portal.deliverables.reject', $deliverable->id), ['reason' => 'Please use the new logo'])
        ->assertRedirect();

    expect($deliverable->refresh()->approval_status)->toBe(Deliverable::APPROVAL_REJECTED)
        ->and($deliverable->rejection_reason)->toBe('Please use the new logo');
});

it('lets a client raise a support request', function () {
    [$org] = deliveryOrganization();
    $client = addMember($org, Role::Client);

    $this->actingAs($client)
        ->post(route('portal.support.tickets.store'), ['subject' => 'Question about the report', 'body' => 'Which period does it cover?'])
        ->assertRedirect();

    $ticket = Ticket::withoutGlobalScope('tenant')->first();
    expect($ticket)->not->toBeNull()
        ->and($ticket->requester_id)->toBe($client->id)
        ->and($ticket->organization_id)->toBe($org->id);
});

it('strips internal notes from the portal ticket view', function () {
    [$org, $owner] = deliveryOrganization();
    app(CurrentOrganization::class)->set($org);
    $tickets = app(TicketService::class);
    $ticket = $tickets->open(['subject' => 'Slow site', 'body' => 'It lags'], $owner);
    $tickets->reply($ticket, 'Public update for the client.', $owner);
    $tickets->reply($ticket, 'Internal: their plugin is the problem.', $owner, internal: true);

    $portal = app(PortalService::class)->ticketsForClient();
    app(CurrentOrganization::class)->forget();

    $bodies = collect($portal[0]['messages'])->pluck('body');
    expect($bodies)->toHaveCount(1)->and($bodies->first())->toBe('Public update for the client.');
});

it('keeps the client role out of the rest of the product', function () {
    [$org] = deliveryOrganization();
    $client = addMember($org, Role::Client);

    // The portal is open to them…
    $this->actingAs($client)->get(route('portal.dashboard'))->assertOk();

    // …but nothing else is.
    $this->actingAs($client)->get(route('crm.contacts.index'))->assertForbidden();
    $this->actingAs($client)->get(route('projects.index'))->assertForbidden();
    $this->actingAs($client)->get(route('support.index'))->assertForbidden();
    $this->actingAs($client)->get(route('strategy.index'))->assertForbidden();
    $this->actingAs($client)->get(route('platform.dashboard'))->assertForbidden();
});

it('keeps non-client roles out of the portal', function () {
    [$org] = deliveryOrganization();
    $analyst = addMember($org, Role::Analyst);

    $this->actingAs($analyst)->get(route('portal.dashboard'))->assertForbidden();
});
