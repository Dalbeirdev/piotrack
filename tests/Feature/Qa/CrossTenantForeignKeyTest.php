<?php

declare(strict_types=1);

/**
 * QA §12 / §47 - cross-tenant foreign keys accepted by unscoped validation.
 *
 * Many controllers validate a foreign key with a bare `exists` rule. Laravel's
 * exists rule runs a raw query, so the Eloquent global tenant scope never sees
 * it and an id belonging to another organization validates cleanly.
 *
 * Where the controller then re-queries through Eloquent (Contact::findOrFail),
 * the global scope catches it and the request 404s - covered in
 * TenantIsolationAttackTest. Where the controller instead stores the validated
 * id straight onto a model, nothing catches it.
 *
 * Attacker: Acme Managed IT Services.  Victim: Northstar Cybersecurity.
 */

use App\Models\Form;
use App\Models\LandingPage;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->northstar, $this->northstarOwner] = makeOrganization('Northstar Cybersecurity');
    subscribeOrganization($this->northstar, 'professional');
    app(CurrentOrganization::class)->set($this->northstar);

    $this->victimForm = Form::create([
        'name' => 'Northstar classified intake',
        'slug' => 'northstar-classified-intake',
        'fields' => [
            ['name' => 'clearance_level', 'type' => 'text', 'required' => true],
            ['name' => 'contract_number', 'type' => 'text', 'required' => true],
        ],
        'lifecycle_stage' => 'lead',
        'status' => 'published',
    ]);

    app(CurrentOrganization::class)->forget();

    [$this->acme, $this->attacker] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->acme, 'professional');
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('refuses a landing page bound to another tenant form', function () {
    $response = $this->actingAs($this->attacker)->post(route('marketing.landing-pages.store'), [
        'name' => 'Acme Philadelphia CMMC',
        'headline' => 'Managed IT for Philadelphia manufacturers',
        'form_id' => $this->victimForm->id,
    ]);

    $response->assertSessionHasErrors('form_id');

    $page = LandingPage::withoutGlobalScope('tenant')->where('name', 'Acme Philadelphia CMMC')->first();

    expect($page?->form_id)->not->toBe($this->victimForm->id);
});

it('refuses to repoint an existing landing page at another tenant form', function () {
    app(CurrentOrganization::class)->set($this->acme);
    $ownForm = Form::create([
        'name' => 'Acme intake', 'slug' => 'acme-intake',
        'fields' => [], 'lifecycle_stage' => 'lead', 'status' => 'published',
    ]);
    $page = LandingPage::create([
        'name' => 'Acme page', 'slug' => 'acme-page',
        'headline' => 'Hello', 'form_id' => $ownForm->id, 'status' => 'draft',
    ]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->attacker)->patch(route('marketing.landing-pages.update', $page->id), [
        'name' => 'Acme page',
        'headline' => 'Hello',
        'form_id' => $this->victimForm->id,
    ])->assertSessionHasErrors('form_id');

    expect($page->fresh()->form_id)->toBe($ownForm->id);
});
