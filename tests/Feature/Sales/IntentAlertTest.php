<?php

use App\Models\AlertRule;
use App\Models\Contact;
use App\Models\IntentSignal;
use App\Models\SalesAlert;
use App\Services\Sales\AlertService;
use App\Services\Sales\IntentService;
use App\Support\CurrentOrganization;

it('records intent signals and computes a score + next action', function () {
    [$org] = salesOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'A', 'email' => 'a@x.com']);
    $service = app(IntentService::class);

    $service->record($contact, 'pricing_view', 15);
    $service->record($contact, 'download', 10);

    expect($service->intentScore($contact))->toBe(25)
        ->and($service->isHighIntent($contact))->toBeTrue()
        ->and($service->nextAction($contact))->toBe('Follow up on the downloaded asset'); // latest signal
    app(CurrentOrganization::class)->forget();
});

it('fires an alert at the score threshold and dedupes', function () {
    [$org] = salesOrganization();
    app(CurrentOrganization::class)->set($org);
    AlertRule::create(['name' => 'Hot lead', 'trigger' => 'score_threshold', 'threshold' => 50, 'channel' => 'in_app', 'is_active' => true]);
    $contact = Contact::create(['first_name' => 'B', 'email' => 'b@x.com', 'lead_score' => 60]);
    $service = app(AlertService::class);

    expect($service->evaluate($contact))->toBe(1)
        ->and($service->evaluate($contact))->toBe(0); // deduped while unread
    expect(SalesAlert::withoutGlobalScope('tenant')->where('contact_id', $contact->id)->count())->toBe(1);
    app(CurrentOrganization::class)->forget();
});

it('records a signal and fires a high-intent alert via the controller', function () {
    [$org, $owner] = salesOrganization();
    app(CurrentOrganization::class)->set($org);
    AlertRule::create(['name' => 'Intent', 'trigger' => 'high_intent', 'threshold' => 20, 'channel' => 'in_app', 'is_active' => true]);
    $contact = Contact::create(['first_name' => 'C', 'email' => 'c@x.com']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('sales.intent.store'), ['contact_id' => $contact->id, 'type' => 'pricing_view', 'weight' => 25])->assertRedirect();

    expect(IntentSignal::withoutGlobalScope('tenant')->where('contact_id', $contact->id)->count())->toBe(1);
    expect(SalesAlert::withoutGlobalScope('tenant')->where('contact_id', $contact->id)->exists())->toBeTrue();
});
