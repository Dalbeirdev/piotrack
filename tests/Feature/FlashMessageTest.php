<?php

use App\Models\Contact;
use App\Support\CurrentOrganization;

/**
 * Regression: `share()` did not expose session flash, so every
 * `back()->with('status', …)` confirmation in the app (147 of them across
 * Stages 2–12) was invisible to the user.
 */
it('shares the status flash with the client', function () {
    [$org, $owner] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Ann', 'email' => 'ann@x.com']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)
        ->post(route('ai.agent.crm-update'), ['contact_id' => $contact->id, 'changes' => ['title' => 'Director']])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('ai.actions.index'))
        ->assertInertia(fn ($page) => $page->where('flash.status', 'Change proposed — it needs approval before it is applied.'));
});

it('shares an ai_result flash with the client', function () {
    [$org, $owner] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Bob', 'email' => 'bob@x.com']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)
        ->post(route('ai.agent.run'), ['contact_id' => $contact->id, 'task' => 'qualify'])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('ai.agent.index'))
        ->assertInertia(fn ($page) => $page->has('flash.ai_result.qualified'));
});

it('shares a null flash when nothing was flashed', function () {
    [, $owner] = aiOrganization();

    $this->actingAs($owner)
        ->get(route('ai.dashboard'))
        ->assertInertia(fn ($page) => $page->where('flash.status', null));
});
