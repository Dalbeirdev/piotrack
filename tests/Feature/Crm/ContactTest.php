<?php

use App\Authorization\Role;
use App\Billing\Entitlements;
use App\Billing\Feature;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Services\SubscriptionService;
use App\Support\CurrentOrganization;

it('lists, creates and shows contacts', function () {
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)->get(route('crm.contacts.index'))->assertOk()
        ->assertInertia(fn ($p) => $p->component('crm/contacts/index'));

    $this->actingAs($owner)
        ->post(route('crm.contacts.store'), ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com'])
        ->assertRedirect();

    $contact = Contact::withoutGlobalScope('tenant')->firstWhere('email', 'ada@example.com');
    expect($contact)->not->toBeNull()->and($contact->organization_id)->toBe($org->id);
    expect(AuditLog::where('action', 'crm.contact.created')->exists())->toBeTrue();
});

it('rejects a duplicate contact email in the organization', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    Contact::create(['first_name' => 'Existing', 'email' => 'dupe@example.com']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)
        ->post(route('crm.contacts.store'), ['first_name' => 'New', 'email' => 'dupe@example.com'])
        ->assertSessionHasErrors('email');

    expect(Contact::withoutGlobalScope('tenant')->where('email', 'dupe@example.com')->count())->toBe(1);
});

it('isolates contacts across tenants', function () {
    [$orgA, $ownerA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');
    app(CurrentOrganization::class)->set($orgB);
    $contactB = Contact::create(['first_name' => 'Secret', 'email' => 'b@example.com']);
    app(CurrentOrganization::class)->forget();

    // Cross-tenant show → 404 (tenant-scoped binding).
    $this->actingAs($ownerA)->get(route('crm.contacts.show', $contactB->id))->assertNotFound();

    // List never leaks tenant B's contact.
    $this->actingAs($ownerA)->get(route('crm.contacts.index'))->assertInertia(fn ($p) => $p
        ->where('contacts.data', fn ($rows) => collect($rows)->doesntContain('email', 'b@example.com')));
});

it('updates and deletes a contact', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Edit', 'email' => 'edit@example.com']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->patch(route('crm.contacts.update', $contact->id), ['first_name' => 'Edited'])->assertRedirect();
    expect($contact->refresh()->first_name)->toBe('Edited');

    $this->actingAs($owner)->delete(route('crm.contacts.destroy', $contact->id))->assertRedirect();
    expect(Contact::withoutGlobalScope('tenant')->find($contact->id))->toBeNull();
});

it('associates a contact with a company', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $company = Company::create(['name' => 'Acme']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('crm.contacts.store'), [
        'first_name' => 'Bea', 'email' => 'bea@example.com', 'company_id' => $company->id,
    ])->assertRedirect();

    expect(Contact::withoutGlobalScope('tenant')->firstWhere('email', 'bea@example.com')->company_id)->toBe($company->id);
});

it('keeps crm accessible on the free tier (crm is a baseline feature)', function () {
    [$org, $owner] = makeOrganization();
    // Even with no active subscription, the free fallback grants the crm
    // feature, so the entitlement:crm gate still allows access.
    app(SubscriptionService::class)->cancel($org->activeSubscription(), immediately: true);
    app(Entitlements::class)->forget($org);

    expect(app(Entitlements::class)->feature($org, Feature::Crm))->toBeTrue();
    $this->actingAs($owner)->get(route('crm.contacts.index'))->assertOk();
});

it('forbids a viewer from creating contacts', function () {
    [$org] = makeOrganization();
    $viewer = addMember($org, Role::Viewer); // crm read only

    $this->actingAs($viewer)->post(route('crm.contacts.store'), ['first_name' => 'No'])->assertForbidden();
    $this->actingAs($viewer)->get(route('crm.contacts.index'))->assertOk();
});
