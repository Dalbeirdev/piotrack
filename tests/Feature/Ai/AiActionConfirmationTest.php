<?php

use App\Authorization\Role;
use App\Models\AiAction;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Services\Ai\AiActionService;
use App\Support\CurrentOrganization;

/**
 * AIPF-006 — the human-confirmation boundary. These tests exist to prove that a
 * language model cannot change data or reach a third party on its own.
 */
it('records a proposal as pending without performing it', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    $contact = Contact::create(['first_name' => 'Ann', 'email' => 'ann@x.com', 'title' => 'Office Manager']);
    $action = app(AiActionService::class)->propose('update_crm', 'Retitle Ann', ['changes' => ['title' => 'IT Director']], $contact);
    app(CurrentOrganization::class)->forget();

    expect($action->status)->toBe(AiAction::STATUS_PENDING)
        ->and($action->isSensitive())->toBeTrue()
        // Nothing was applied by proposing.
        ->and($contact->refresh()->title)->toBe('Office Manager')
        ->and(AuditLog::where('action', 'ai.action.proposed')->exists())->toBeTrue();
});

it('refuses to execute a pending action', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    $contact = Contact::create(['first_name' => 'Bob', 'email' => 'bob@x.com', 'title' => 'Analyst']);
    $action = app(AiActionService::class)->propose('update_crm', 'Retitle Bob', ['changes' => ['title' => 'CTO']], $contact);

    expect(fn () => app(AiActionService::class)->execute($action))
        ->toThrow(RuntimeException::class, 'must be confirmed by a human first');
    app(CurrentOrganization::class)->forget();

    expect($contact->refresh()->title)->toBe('Analyst'); // unchanged
});

it('applies the change only after a human confirms', function () {
    [$org, $owner] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    $contact = Contact::create(['first_name' => 'Cara', 'email' => 'cara@x.com', 'title' => 'Manager']);
    $service = app(AiActionService::class);
    $action = $service->propose('update_crm', 'Retitle Cara', ['changes' => ['title' => 'IT Director']], $contact);

    $confirmed = $service->confirm($action, $owner);
    expect($confirmed->status)->toBe(AiAction::STATUS_CONFIRMED)
        ->and($contact->refresh()->title)->toBe('Manager'); // still not applied

    $executed = $service->execute($confirmed);
    app(CurrentOrganization::class)->forget();

    expect($executed->status)->toBe(AiAction::STATUS_EXECUTED)
        ->and($executed->result)->toContain('title')
        ->and($contact->refresh()->title)->toBe('IT Director')
        ->and(AuditLog::where('action', 'ai.action.executed')->exists())->toBeTrue();
});

it('makes rejection terminal', function () {
    [$org, $owner] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    $contact = Contact::create(['first_name' => 'Dan', 'email' => 'dan@x.com', 'title' => 'Clerk']);
    $service = app(AiActionService::class);
    $action = $service->propose('update_crm', 'Retitle Dan', ['changes' => ['title' => 'CEO']], $contact);

    $rejected = $service->reject($action, $owner);

    expect($rejected->status)->toBe(AiAction::STATUS_REJECTED)
        ->and(fn () => $service->execute($rejected))->toThrow(RuntimeException::class)
        ->and(fn () => $service->confirm($rejected, $owner))->toThrow(RuntimeException::class);
    app(CurrentOrganization::class)->forget();

    expect($contact->refresh()->title)->toBe('Clerk');
});

it('executes at most once', function () {
    [$org, $owner] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    $contact = Contact::create(['first_name' => 'Eve', 'email' => 'eve@x.com', 'lead_score' => 0]);
    $service = app(AiActionService::class);
    $action = $service->propose('update_crm', 'Set source', ['changes' => ['lead_source' => 'ai']], $contact);

    $executed = $service->confirmAndExecute($action, $owner);
    $again = $service->execute($executed); // idempotent no-op
    app(CurrentOrganization::class)->forget();

    expect($again->status)->toBe(AiAction::STATUS_EXECUTED)
        ->and($again->executed_at->toIso8601String())->toBe($executed->executed_at->toIso8601String())
        ->and(AuditLog::where('action', 'ai.action.executed')->count())->toBe(1);
});

it('only writes allow-listed fields from a proposal', function () {
    [$org, $owner] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    $contact = Contact::create(['first_name' => 'Fay', 'email' => 'fay@x.com', 'title' => 'Manager']);
    $service = app(AiActionService::class);

    // `email` and `lead_score` are deliberately NOT writable by an AI proposal.
    $action = $service->propose('update_crm', 'Sneaky', [
        'changes' => ['title' => 'Director', 'email' => 'attacker@evil.com', 'lead_score' => 999],
    ], $contact);
    $service->confirmAndExecute($action, $owner);
    app(CurrentOrganization::class)->forget();

    $contact->refresh();
    expect($contact->title)->toBe('Director')
        ->and($contact->email)->toBe('fay@x.com')   // untouched
        ->and($contact->lead_score)->toBe(0);        // untouched
});

it('requires the approve permission to confirm through the route', function () {
    [$org, $owner] = aiOrganization();
    $rep = addMember($org, Role::SalesRepresentative); // may use the agent, may NOT approve

    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Gil', 'email' => 'gil@x.com', 'title' => 'Clerk']);
    $action = app(AiActionService::class)->propose('update_crm', 'Retitle Gil', ['changes' => ['title' => 'CIO']], $contact);
    app(CurrentOrganization::class)->forget();

    // A rep can see the queue but cannot approve.
    $this->actingAs($rep)->get(route('ai.actions.index'))->assertOk();
    $this->actingAs($rep)->post(route('ai.actions.approve', $action->id))->assertForbidden();
    expect($contact->refresh()->title)->toBe('Clerk');

    // An owner can.
    $this->actingAs($owner)->post(route('ai.actions.approve', $action->id))->assertRedirect();
    expect($contact->refresh()->title)->toBe('CIO')
        ->and($action->refresh()->confirmed_by)->toBe($owner->id);
});

it('rejects through the route without applying anything', function () {
    [$org, $owner] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Hal', 'email' => 'hal@x.com', 'title' => 'Clerk']);
    $action = app(AiActionService::class)->propose('update_crm', 'Retitle Hal', ['changes' => ['title' => 'CFO']], $contact);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('ai.actions.reject', $action->id))->assertRedirect();

    expect($action->refresh()->status)->toBe(AiAction::STATUS_REJECTED)
        ->and($contact->refresh()->title)->toBe('Clerk');
});

it('never sends email directly, only records approval to send', function () {
    [$org, $owner] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    $service = app(AiActionService::class);
    $action = $service->propose('send_email', 'Send intro', ['to' => 'x@y.com', 'subject' => 'Hi', 'body' => 'Hello']);
    $executed = $service->confirmAndExecute($action, $owner);
    app(CurrentOrganization::class)->forget();

    expect($executed->status)->toBe(AiAction::STATUS_EXECUTED)
        ->and($executed->result)->toContain('queued to the messaging pipeline');
});
