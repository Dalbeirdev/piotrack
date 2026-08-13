<?php

use App\Authorization\Role;
use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Services\OrganizationService;
use Illuminate\Validation\ValidationException;

it('counts member seats as active members plus pending invitations', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'growth');

    addMember($org, Role::Admin); // 2 active
    app(OrganizationService::class)->invite($org, $owner, 'pending@example.com', Role::Viewer); // +1 pending

    expect(app(UsageMeter::class)->usage($org, Limit::Members))->toBe(3)
        ->and(app(UsageMeter::class)->remaining($org, Limit::Members))->toBe(7);
});

it('enforces the member seat limit on invite', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'starter'); // limit 3
    $svc = app(OrganizationService::class);

    // Owner is seat 1; two invites fill seats 2 and 3.
    $svc->invite($org, $owner, 'a@example.com', Role::Viewer);
    $svc->invite($org, $owner, 'b@example.com', Role::Viewer);

    // The fourth seat is over the limit.
    expect(fn () => $svc->invite($org, $owner, 'c@example.com', Role::Viewer))
        ->toThrow(ValidationException::class);
});

it('reports usage over unlimited limits as remaining null', function () {
    [$org] = makeOrganization();
    subscribeOrganization($org, 'enterprise'); // unlimited members

    expect(app(UsageMeter::class)->remaining($org, Limit::Members))->toBeNull()
        ->and(app(UsageMeter::class)->withinLimit($org, Limit::Members, 9999))->toBeTrue();
});

it('accumulates counter-based usage for the current period', function () {
    [$org] = makeOrganization();
    subscribeOrganization($org, 'growth');
    $meter = app(UsageMeter::class);

    $meter->increment($org, Limit::Emails, 5);
    $meter->increment($org, Limit::Emails, 3);

    expect($meter->usage($org, Limit::Emails))->toBe(8);
});

it('surfaces a usage summary on the billing portal', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'growth');

    $this->actingAs($owner)->get(route('billing.index'))->assertInertia(fn ($page) => $page
        ->where('usage', fn ($usage) => collect($usage)->contains(fn ($row) => $row['key'] === 'members' && $row['limit'] === 10)),
    );
});
