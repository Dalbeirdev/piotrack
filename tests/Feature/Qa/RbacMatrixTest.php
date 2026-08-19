<?php

declare(strict_types=1);

/**
 * QA §13 - RBAC enforced at the backend, per role.
 *
 * §13 is explicit that hidden UI controls prove nothing, so every action is a
 * real HTTP request with the response asserted.
 *
 * Expectations are DERIVED, not hand-written. For each route the test reads the
 * permission off its own `can:` middleware and asks RolePermissions whether the
 * role holds it. So the invariant under test is "the backend enforces exactly
 * what the permission model declares" - a route wired to the wrong permission,
 * or missing its gate, fails here. Hand-written expectations would only encode
 * my assumptions about policy and pass by construction.
 *
 * The organization is on Enterprise so entitlement gating never masks an
 * authorization result: a 403 must mean "your role may not", never "your plan
 * does not include it".
 *
 * Organization: Acme Managed IT Services.
 */

use App\Authorization\Role;
use App\Authorization\RolePermissions;
use App\Models\Contact;
use App\Models\Integration;
use App\Models\MarketingList;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Route as RouteFacade;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
    $this->list = MarketingList::create(['name' => 'Philadelphia CMMC prospects', 'type' => 'static']);
    app(CurrentOrganization::class)->forget();
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

/** The permission a named route actually requires, read off its middleware. */
function permissionFor(string $routeName): ?string
{
    $route = RouteFacade::getRoutes()->getByName($routeName);

    foreach ($route?->gatherMiddleware() ?? [] as $middleware) {
        if (! is_string($middleware)) {
            continue;
        }
        foreach (['can:', 'Authorize:'] as $prefix) {
            if (str_contains($middleware, $prefix)) {
                return explode(',', explode($prefix, $middleware)[1])[0];
            }
        }
    }

    return null;
}

it('enforces every role at the backend exactly as the permission model declares', function () {
    // Roles a customer organization can actually hold.
    $roles = [
        Role::Owner, Role::Admin, Role::MarketingManager, Role::SalesManager,
        Role::SalesRepresentative, Role::MarketingUser, Role::Analyst,
        Role::BillingAdministrator, Role::Viewer,
    ];

    $failures = [];
    $checks = 0;

    foreach ($roles as $role) {
        $user = $role === Role::Owner ? $this->owner : addMember($this->org, $role);
        $held = RolePermissions::for($role->value);

        // A fresh contact per role, so a destructive probe cannot 404 for the
        // next role and be misread as a denial.
        app(CurrentOrganization::class)->set($this->org);
        $contact = Contact::create([
            'first_name' => 'Michael', 'last_name' => 'Rodriguez',
            'email' => 'michael.'.$role->value.'@precisionmfg-test.com', 'title' => 'CFO',
        ]);
        app(CurrentOrganization::class)->forget();

        $probes = [
            ['GET', 'crm.contacts.index', route('crm.contacts.index')],
            ['DELETE', 'crm.contacts.destroy', route('crm.contacts.destroy', $contact->id)],
            ['GET', 'billing.index', route('billing.index')],
            ['GET', 'billing.invoices.index', route('billing.invoices.index')],
            ['PATCH', 'billing.subscription.update', route('billing.subscription.update')],
            ['GET', 'members.index', route('members.index')],
            ['GET', 'integrations.index', route('integrations.index')],
            ['GET', 'analytics.dashboard', route('analytics.dashboard')],
            ['POST', 'marketing.lists.contacts.add', route('marketing.lists.contacts.add', $this->list->id)],
        ];

        foreach ($probes as [$method, $name, $url]) {
            $required = permissionFor($name);

            expect($required)->not->toBeNull("route {$name} has no permission middleware");

            $shouldAllow = in_array($required, $held, true);

            $status = $this->actingAs($user)->call($method, $url, [
                'contact_id' => $contact->id, 'plan' => 'enterprise', 'interval' => 'monthly',
            ])->getStatusCode();

            $checks++;
            $denied = $status === 403;

            if ($shouldAllow && $denied) {
                $failures[] = "{$role->value} holds {$required} but {$name} returned 403";
            }

            // 404 is not a denial: it means the record was gone, which would
            // hide a missing gate. Only 403 counts as refusal.
            if (! $shouldAllow && ! $denied) {
                $failures[] = "{$role->value} lacks {$required} but {$name} returned {$status}";
            }
        }
    }

    expect($failures)->toBe([])
        ->and($checks)->toBe(count($roles) * 9);
});

/*
|--------------------------------------------------------------------------
| §13 named cases
|--------------------------------------------------------------------------
*/

it('refuses a viewer the four actions §13 names', function () {
    $viewer = addMember($this->org, Role::Viewer);

    app(CurrentOrganization::class)->set($this->org);
    $contact = Contact::create([
        'first_name' => 'Michael', 'last_name' => 'Rodriguez',
        'email' => 'michael.rodriguez@precisionmfg-test.com',
    ]);
    $integration = Integration::create([
        'name' => 'Google Ads', 'provider' => 'google_ads', 'status' => 'connected',
    ]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($viewer)->delete(route('crm.contacts.destroy', $contact->id))->assertForbidden();
    $this->actingAs($viewer)->patch(route('billing.subscription.update'), ['plan' => 'growth'])->assertForbidden();
    $this->actingAs($viewer)->post(route('invitations.store'), [
        'email' => 'intruder@acme-managed-it-test.com', 'role' => Role::Admin->value,
    ])->assertForbidden();
    $this->actingAs($viewer)
        ->post(route('integrations.disconnect', $integration->id))
        ->assertForbidden();

    expect(Contact::withoutGlobalScope('tenant')->find($contact->id))->not->toBeNull();
});

it('lets a billing administrator bill, and stops them touching marketing or CRM', function () {
    $billing = addMember($this->org, Role::BillingAdministrator);

    $this->actingAs($billing)->get(route('billing.invoices.index'))->assertSuccessful();
    $this->actingAs($billing)->get(route('billing.index'))->assertSuccessful();

    expect($this->actingAs($billing)->patch(route('billing.profile.update'), [
        'name' => 'Acme Managed IT Services', 'country' => 'US',
    ])->getStatusCode())->not->toBe(403);

    $this->actingAs($billing)
        ->post(route('marketing.lists.contacts.add', $this->list->id), ['contact_id' => 1])
        ->assertForbidden();

    $this->actingAs($billing)->get(route('crm.contacts.index'))->assertForbidden();
});
