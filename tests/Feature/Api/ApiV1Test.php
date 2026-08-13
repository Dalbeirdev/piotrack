<?php

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Services\OrganizationService;
use App\Support\CurrentOrganization;
use Laravel\Sanctum\Sanctum;

/**
 * Subscribe an org to a plan that includes the `api` feature and return
 * [$org, $owner] with a seeded contact for read tests.
 */
function apiOrganization(): array
{
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'professional');

    return [$org, $owner];
}

it('requires authentication', function () {
    $this->getJson('/api/v1/contacts')->assertUnauthorized();
});

it('lists contacts in a paginated envelope', function () {
    [$org, $owner] = apiOrganization();
    app(CurrentOrganization::class)->set($org);
    Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com']);
    app(CurrentOrganization::class)->forget();

    Sanctum::actingAs($owner);

    $response = $this->getJson('/api/v1/contacts', ['X-Organization-Id' => (string) $org->id]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'first_name', 'name', 'email', 'company', 'owner']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ])
        ->assertJsonPath('data.0.email', 'ada@example.com');

    expect($response->headers->get('X-Request-Id'))->not->toBeNull();
});

it('shows a single contact in a data envelope', function () {
    [$org, $owner] = apiOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Grace', 'email' => 'grace@example.com']);
    app(CurrentOrganization::class)->forget();

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/contacts/{$contact->id}", ['X-Organization-Id' => (string) $org->id])
        ->assertOk()
        ->assertJsonPath('data.id', $contact->id)
        ->assertJsonPath('data.email', 'grace@example.com');
});

it('creates a contact and records an audit event', function () {
    [$org, $owner] = apiOrganization();
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/contacts', ['first_name' => 'New', 'email' => 'new@example.com'], ['X-Organization-Id' => (string) $org->id])
        ->assertCreated()
        ->assertJsonPath('data.email', 'new@example.com');

    $contact = Contact::withoutGlobalScope('tenant')->firstWhere('email', 'new@example.com');
    expect($contact)->not->toBeNull()->and($contact->organization_id)->toBe($org->id);
    expect(AuditLog::withoutGlobalScope('tenant')->where('action', 'crm.contact.created')->exists())->toBeTrue();
});

it('validates the request body on create', function () {
    [$org, $owner] = apiOrganization();
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/contacts', ['email' => 'not-an-email'], ['X-Organization-Id' => (string) $org->id])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => ['first_name']]);
});

it('blocks access when the plan does not include the api feature', function () {
    // makeOrganization starts a Growth trial, which does NOT grant `api`.
    [$org, $owner] = makeOrganization();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/contacts', ['X-Organization-Id' => (string) $org->id])
        ->assertForbidden();
});

it('rejects a header for an organization the caller does not belong to', function () {
    [, $owner] = apiOrganization();
    [$otherOrg] = makeOrganization('Other');

    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/contacts', ['X-Organization-Id' => (string) $otherOrg->id])
        ->assertStatus(400)
        ->assertJsonPath('message', 'You are not a member of the requested organization.');
});

it('enforces permissions on write endpoints', function () {
    [$org] = apiOrganization();
    $viewer = addMember($org, Role::Viewer); // read-only CRM permissions

    Sanctum::actingAs($viewer);

    // Viewer can read.
    $this->getJson('/api/v1/contacts', ['X-Organization-Id' => (string) $org->id])->assertOk();

    // Viewer cannot create.
    $this->postJson('/api/v1/contacts', ['first_name' => 'Nope'], ['X-Organization-Id' => (string) $org->id])
        ->assertForbidden();
});

it('replays an idempotent create instead of duplicating it', function () {
    [$org, $owner] = apiOrganization();
    Sanctum::actingAs($owner);

    $headers = ['X-Organization-Id' => (string) $org->id, 'Idempotency-Key' => 'abc-123'];
    $payload = ['first_name' => 'Once', 'email' => 'once@example.com'];

    $first = $this->postJson('/api/v1/contacts', $payload, $headers)->assertCreated();
    $second = $this->postJson('/api/v1/contacts', $payload, $headers)->assertCreated();

    expect($second->headers->get('Idempotent-Replayed'))->toBe('true');
    expect($first->json('data.id'))->toBe($second->json('data.id'));
    expect(Contact::withoutGlobalScope('tenant')->where('email', 'once@example.com')->count())->toBe(1);
});

it('isolates data across tenants for the same user', function () {
    [$orgA, $user] = apiOrganization();
    // Give the same user a second org that also has the api feature.
    $orgB = app(OrganizationService::class)->create($user, 'B');
    subscribeOrganization($orgB, 'professional');

    app(CurrentOrganization::class)->set($orgA);
    Contact::create(['first_name' => 'AOnly', 'email' => 'a-only@example.com']);
    app(CurrentOrganization::class)->forget();

    Sanctum::actingAs($user);

    // Querying org B must not see org A's contact.
    $this->getJson('/api/v1/contacts', ['X-Organization-Id' => (string) $orgB->id])
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});
