<?php

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Support\CurrentOrganization;

it('converts a lead into a contact, company and deal', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $lead = Lead::create(['first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => 'grace@navy.mil', 'company_name' => 'US Navy', 'source' => 'Referral']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)
        ->post(route('crm.leads.convert', $lead->id), ['create_deal' => true, 'deal_value' => 5000])
        ->assertRedirect();

    $lead->refresh();
    expect($lead->status)->toBe('converted')
        ->and($lead->converted_contact_id)->not->toBeNull()
        ->and($lead->converted_deal_id)->not->toBeNull();

    expect(Contact::withoutGlobalScope('tenant')->where('email', 'grace@navy.mil')->exists())->toBeTrue()
        ->and(Company::withoutGlobalScope('tenant')->where('name', 'US Navy')->exists())->toBeTrue()
        ->and(Deal::withoutGlobalScope('tenant')->where('value', 500000)->exists())->toBeTrue();

    expect(AuditLog::where('action', 'crm.lead.converted')->exists())->toBeTrue();
});

it('does not re-convert an already converted lead', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $lead = Lead::create(['first_name' => 'Done', 'status' => 'converted']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('crm.leads.convert', $lead->id))->assertSessionHasErrors('lead');
});

it('creates a deal in the default pipeline and moves stages', function () {
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)->post(route('crm.deals.store'), ['name' => 'Big deal', 'value' => 10000])->assertRedirect();
    $deal = Deal::withoutGlobalScope('tenant')->firstWhere('name', 'Big deal');
    expect($deal)->not->toBeNull()->and($deal->value)->toBe(1000000);

    // Move to the Won stage → status won + closed.
    $wonStage = $deal->pipeline->stages()->where('is_won', true)->first();
    $this->actingAs($owner)->patch(route('crm.deals.stage', $deal->id), ['stage_id' => $wonStage->id])->assertRedirect();

    $deal->refresh();
    expect($deal->status)->toBe('won')->and($deal->closed_at)->not->toBeNull();
    expect(AuditLog::where('action', 'crm.deal.stage_changed')->exists())->toBeTrue();
});

it('blocks moving a deal to another pipeline stage', function () {
    [$orgA, $ownerA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');

    app(CurrentOrganization::class)->set($orgA);
    $dealA = Deal::factory()->create(['organization_id' => $orgA->id]);
    app(CurrentOrganization::class)->set($orgB);
    $stageB = App\Models\Pipeline::where('is_default', true)->first()->stages()->first();
    app(CurrentOrganization::class)->forget();

    $this->actingAs($ownerA)->patch(route('crm.deals.stage', $dealA->id), ['stage_id' => $stageB->id])
        ->assertSessionHasErrors('stage_id');
});

it('logs polymorphic activities on a contact', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Time', 'email' => 'time@line.com']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('crm.activities.store'), [
        'subject_type' => 'contact', 'subject_id' => $contact->id, 'type' => 'call', 'body' => 'Left a voicemail',
    ])->assertRedirect();

    expect($contact->activities()->count())->toBe(1)
        ->and($contact->activities()->first()->subject_type)->toBe('contact');
});

it('cannot attach an activity to another tenant record', function () {
    [$orgA, $ownerA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');
    app(CurrentOrganization::class)->set($orgB);
    $contactB = Contact::create(['first_name' => 'B', 'email' => 'b@b.com']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($ownerA)->post(route('crm.activities.store'), [
        'subject_type' => 'contact', 'subject_id' => $contactB->id, 'type' => 'note', 'body' => 'x',
    ])->assertNotFound();
});

it('forbids a viewer from managing activities', function () {
    [$org] = makeOrganization();
    $viewer = addMember($org, Role::Viewer);
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'V', 'email' => 'v@v.com']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($viewer)->post(route('crm.activities.store'), [
        'subject_type' => 'contact', 'subject_id' => $contact->id, 'type' => 'note', 'body' => 'x',
    ])->assertForbidden();
});
