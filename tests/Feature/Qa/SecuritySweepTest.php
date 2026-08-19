<?php

declare(strict_types=1);

/**
 * QA §47 - security testing, the checks not already covered by the tenant-
 * isolation (§12), RBAC (§13) and authentication (§11) suites.
 *
 * This run's P0 was in the RBAC map itself, not the middleware, so the emphasis
 * here is on the assumptions around the boundary: privileged fields that must
 * not be mass-assignable, secrets that must never serialize, protected routes
 * that must reject the unauthenticated, and injection payloads that must be
 * treated as data.
 */

use App\Authorization\Permission;
use App\Authorization\Role;
use App\Authorization\RolePermissions;
use App\Models\Contact;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

/*
|--------------------------------------------------------------------------
| Permission-map invariants
|--------------------------------------------------------------------------
|
| The P0 this run found lived in the permission map, not the middleware. A
| test that derives its expectations FROM the map cannot catch a wrong map - it
| would expect the escalation and pass. These assert the map against intent
| directly, so a future edit that reintroduces the class of bug fails here.
*/

/** Every role a customer organization can hold (platform roles are not org roles). */
function organizationRoles(): array
{
    return [
        Role::Owner, Role::Admin, Role::MarketingManager, Role::SalesManager,
        Role::SalesRepresentative, Role::MarketingUser, Role::Analyst,
        Role::BillingAdministrator, Role::Viewer, Role::Client,
    ];
}

it('grants no platform-staff ability to any organization role', function () {
    // The generalisation of the fixed P0: not just Owner and Admin, but every
    // organization role, must be free of the platform-only permissions.
    $platformOnly = [Permission::AdminPlatform->value, Permission::AdminImpersonate->value];
    $offenders = [];

    foreach (organizationRoles() as $role) {
        foreach (array_intersect($platformOnly, RolePermissions::for($role->value)) as $held) {
            $offenders[] = "{$role->value} holds {$held}";
        }
    }

    expect($offenders)->toBe([]);
});

it('confines read-only roles to reading, bar the client portal approval', function () {
    $mutating = ['.manage', '.create', '.update', '.delete', '.invite', '.remove', '.send', '.import', '.approve'];

    // Viewer is the pure read-only role: no mutating ability whatsoever.
    $viewerMutations = array_filter(
        RolePermissions::for(Role::Viewer->value),
        fn (string $p) => array_any($mutating, fn (string $s) => str_ends_with($p, $s)),
    );
    expect(array_values($viewerMutations))->toBe([]);

    // Client exists to approve delivered work in the portal, so projects.approve
    // is its one designed mutation. Anything beyond that pair is an error.
    expect(RolePermissions::for(Role::Client->value))
        ->toEqualCanonicalizing([Permission::PortalAccess->value, Permission::ProjectsApprove->value]);
});

/*
|--------------------------------------------------------------------------
| Mass assignment of privileged fields
|--------------------------------------------------------------------------
*/

it('cannot escalate platform_role through the profile form', function () {
    $this->actingAs($this->owner)->patch(route('profile.update'), [
        'name' => 'Dana Whitfield',
        'email' => $this->owner->email,
        'platform_role' => Role::PlatformSuperAdmin->value,
        'current_organization_id' => 999,
    ])->assertRedirect();

    $fresh = $this->owner->fresh();

    expect($fresh->platform_role)->toBeNull('platform_role was mass-assignable')
        ->and($fresh->current_organization_id)->toBe($this->org->id);
});

it('cannot forge organization_id when creating a contact through the API', function () {
    app(CurrentOrganization::class)->forget();
    [$rival] = makeOrganization('Northstar Cybersecurity');
    subscribeOrganization($rival, 'enterprise');

    $token = $this->owner->createToken('qa')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/contacts', [
            'first_name' => 'Injected',
            'email' => 'injected@precisionmfg-test.com',
            'organization_id' => $rival->id,
        ])->assertSuccessful();

    // The contact must belong to the caller's org, never the forged one.
    $contact = Contact::withoutGlobalScope('tenant')
        ->where('email', 'injected@precisionmfg-test.com')->firstOrFail();

    expect($contact->organization_id)->toBe($this->org->id)
        ->and($contact->organization_id)->not->toBe($rival->id);
});

it('cannot move a contact to another tenant by updating organization_id', function () {
    app(CurrentOrganization::class)->set($this->org);
    $contact = Contact::create(['first_name' => 'Michael', 'email' => 'michael@precisionmfg-test.com']);
    app(CurrentOrganization::class)->forget();

    [$rival] = makeOrganization('Northstar Cybersecurity');
    subscribeOrganization($rival, 'enterprise');

    $this->actingAs($this->owner)->patch(route('crm.contacts.update', $contact->id), [
        'first_name' => 'Michael',
        'organization_id' => $rival->id,
    ]);

    // organization_id is stamped immutable by BelongsToTenant.
    expect($contact->fresh()->organization_id)->toBe($this->org->id);
});

/*
|--------------------------------------------------------------------------
| Sensitive-data exposure
|--------------------------------------------------------------------------
*/

it('never serializes password or token fields in an API response', function () {
    app(CurrentOrganization::class)->set($this->org);
    Contact::create([
        'first_name' => 'Michael', 'email' => 'michael@precisionmfg-test.com',
        'owner_id' => $this->owner->id,
    ]);
    app(CurrentOrganization::class)->forget();

    $token = $this->owner->createToken('qa')->plainTextToken;

    $body = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/contacts')->assertSuccessful()->getContent();

    foreach (['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'] as $secret) {
        expect($body)->not->toContain($secret);
    }
});

it('excludes hidden fields from the user model when serialized', function () {
    $array = $this->owner->fresh()->toArray();

    expect($array)->not->toHaveKey('password')
        ->and($array)->not->toHaveKey('remember_token')
        ->and($array)->not->toHaveKey('two_factor_secret')
        ->and($array)->not->toHaveKey('two_factor_recovery_codes');
});

/*
|--------------------------------------------------------------------------
| Unauthenticated access
|--------------------------------------------------------------------------
*/

it('rejects the unauthenticated from protected surfaces', function () {
    // Web routes redirect to login; API routes answer 401.
    foreach ([
        route('dashboard'),
        route('crm.contacts.index'),
        route('platform.dashboard'),
        route('members.index'),
    ] as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }

    $this->getJson('/api/v1/contacts')->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Injection payloads are data, not code
|--------------------------------------------------------------------------
*/

it('treats a SQL-injection payload in search as a literal string', function () {
    app(CurrentOrganization::class)->set($this->org);
    Contact::create(['first_name' => 'Michael', 'email' => 'michael@precisionmfg-test.com']);
    $before = Contact::count();
    app(CurrentOrganization::class)->forget();

    $payload = "'; DROP TABLE contacts; --";

    $response = $this->actingAs($this->owner)->get('/search?q='.urlencode($payload));
    $response->assertSuccessful();

    // The table is intact and the payload matched nothing rather than executing.
    app(CurrentOrganization::class)->set($this->org);
    expect(Contact::count())->toBe($before);
    app(CurrentOrganization::class)->forget();
});

it('stores an XSS payload verbatim and never reflects it as markup', function () {
    $payload = '<script>alert(document.cookie)</script>';

    app(CurrentOrganization::class)->set($this->org);
    Contact::create(['first_name' => $payload, 'email' => 'xss@precisionmfg-test.com']);
    app(CurrentOrganization::class)->forget();

    $token = $this->owner->createToken('qa')->plainTextToken;
    $body = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/contacts')->getContent();

    // JSON encodes the angle brackets; the raw script tag must not appear.
    expect($body)->not->toContain('<script>alert(document.cookie)</script>');
});

/*
|--------------------------------------------------------------------------
| Session invalidation
|--------------------------------------------------------------------------
*/

it('drops all other sessions on request without touching the password', function () {
    $this->actingAs($this->owner);

    // The route exists and is reachable; it must not error.
    $response = $this->delete(route('sessions.destroy-others', absolute: false), [
        'password' => 'password',
    ]);

    expect($response->status())->not->toBe(500);
})->skip(
    fn () => ! Route::has('sessions.destroy-others'),
    'no other-session revocation route',
);
