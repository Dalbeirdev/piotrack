<?php

use App\Models\AiAction;
use App\Models\AiConversation;
use App\Models\AiPromptTemplate;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Contact;
use App\Services\Ai\AiSalesAgent;
use App\Services\Ai\PromptRegistry;
use App\Support\CurrentOrganization;

it('qualifies a lead and returns a structured verdict', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Ann', 'email' => 'ann@x.com', 'lifecycle_stage' => 'mql', 'lead_score' => 60]);

    $result = app(AiSalesAgent::class)->qualify($contact);
    app(CurrentOrganization::class)->forget();

    expect($result)->toHaveKeys(['qualified', 'reason', 'raw'])
        ->and($result['qualified'])->toBeBool()
        ->and($result['reason'])->not->toBeEmpty();
});

it('scores a lead advisorily without touching the deterministic score', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Bob', 'email' => 'bob@x.com', 'lead_score' => 42]);

    $result = app(AiSalesAgent::class)->scoreLead($contact);
    app(CurrentOrganization::class)->forget();

    expect($result['score'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100)
        ->and($result['reason'])->not->toBeEmpty()
        // The Stage 10 deterministic score is untouched by the AI opinion.
        ->and($contact->refresh()->lead_score)->toBe(42);
});

it('holds a chat exchange and stores both turns', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    $conversation = AiConversation::create(['channel' => 'web', 'status' => 'open', 'started_at' => now()]);
    $reply = app(AiSalesAgent::class)->reply($conversation, 'Do you support Microsoft 365?');
    app(CurrentOrganization::class)->forget();

    expect($reply->role)->toBe('assistant')
        ->and($reply->body)->not->toBeEmpty()
        ->and($reply->ai_request_id)->not->toBeNull()   // linked to the recorded call
        ->and($conversation->messages()->count())->toBe(2);
});

it('summarizes a conversation onto the record', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    $conversation = AiConversation::create(['channel' => 'web', 'status' => 'open', 'started_at' => now()]);
    $agent = app(AiSalesAgent::class);
    $agent->reply($conversation, 'We need better response times.');
    $summary = $agent->summarizeConversation($conversation);
    app(CurrentOrganization::class)->forget();

    expect($summary)->not->toBeEmpty()
        ->and($conversation->refresh()->summary)->toBe($summary);
});

it('drafts an email without sending anything', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Cara', 'email' => 'cara@x.com']);

    $draft = app(AiSalesAgent::class)->draftEmail($contact, 'introduce managed services');
    app(CurrentOrganization::class)->forget();

    expect($draft)->toContain('Subject:')
        // Drafting creates no action and sends nothing.
        ->and(AiAction::withoutGlobalScope('tenant')->count())->toBe(0);
});

it('proposes a CRM update rather than applying it', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Dan', 'email' => 'dan@x.com', 'title' => 'Analyst']);

    $action = app(AiSalesAgent::class)->proposeCrmUpdate($contact, ['title' => 'Director']);
    app(CurrentOrganization::class)->forget();

    expect($action->status)->toBe(AiAction::STATUS_PENDING)
        ->and($contact->refresh()->title)->toBe('Analyst');
});

it('proposes a booking rather than creating one', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Eve', 'email' => 'eve@x.com']);
    BookingPage::create(['name' => 'Consult', 'slug' => 'ai-consult', 'meeting_type' => 'consultation', 'duration_minutes' => 30, 'assignment' => 'fixed', 'is_active' => true]);

    $action = app(AiSalesAgent::class)->proposeBooking($contact, ['scheduled_at' => now()->addDay()->toDateTimeString()]);
    app(CurrentOrganization::class)->forget();

    expect($action->status)->toBe(AiAction::STATUS_PENDING)
        ->and(Booking::withoutGlobalScope('tenant')->count())->toBe(0);
});

it('books only after the proposal is approved', function () {
    [$org, $owner] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Fay', 'email' => 'fay@x.com']);
    BookingPage::create(['name' => 'Consult', 'slug' => 'ai-consult-2', 'meeting_type' => 'consultation', 'duration_minutes' => 30, 'assignment' => 'fixed', 'is_active' => true]);
    $action = app(AiSalesAgent::class)->proposeBooking($contact, ['scheduled_at' => now()->addDay()->toDateTimeString()]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('ai.actions.approve', $action->id))->assertRedirect();

    $booking = Booking::withoutGlobalScope('tenant')->first();
    expect($booking)->not->toBeNull()
        ->and($booking->source)->toBe('ai_agent')
        ->and($booking->contact_id)->toBe($contact->id);
});

it('handles an objection and recommends a next action', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Gil', 'email' => 'gil@x.com', 'lifecycle_stage' => 'sql']);
    $agent = app(AiSalesAgent::class);

    expect($agent->handleObjection('Your price is too high.'))->not->toBeEmpty()
        ->and($agent->nextBestAction($contact))->not->toBeEmpty()
        ->and($agent->researchLead($contact))->not->toBeEmpty();
    app(CurrentOrganization::class)->forget();
});

it('runs an agent task through the controller', function () {
    [$org, $owner] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Hal', 'email' => 'hal@x.com']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)
        ->post(route('ai.agent.run'), ['contact_id' => $contact->id, 'task' => 'qualify'])
        ->assertRedirect()
        ->assertSessionHas('ai_result');
});

it('publishes a new prompt version without mutating the old one', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $registry = app(PromptRegistry::class);

    $v1 = $registry->active('sales.qualify');
    $v2 = $registry->publish('sales.qualify', 'Brand new template {{name}}', 'New system');

    expect($v2->version)->toBe(2)
        ->and($v2->is_active)->toBeTrue()
        ->and($v1->refresh()->is_active)->toBeFalse()
        ->and($v1->template)->not->toBe($v2->template) // original preserved
        ->and($registry->render('sales.qualify', ['name' => 'Ann'])['prompt'])->toBe('Brand new template Ann');

    // Roll back to v1.
    $registry->activate('sales.qualify', 1);
    expect($registry->active('sales.qualify')->version)->toBe(1)
        ->and($registry->history('sales.qualify'))->toHaveCount(2);
    app(CurrentOrganization::class)->forget();
});

it('keeps exactly one active version per prompt key', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $registry = app(PromptRegistry::class);

    $registry->active('sales.qualify');
    $registry->publish('sales.qualify', 'v2');
    $registry->publish('sales.qualify', 'v3');
    app(CurrentOrganization::class)->forget();

    expect(AiPromptTemplate::withoutGlobalScope('tenant')->where('key', 'sales.qualify')->where('is_active', true)->count())->toBe(1);
});
