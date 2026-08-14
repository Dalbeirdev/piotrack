<?php

use App\Models\Citation;
use App\Models\Organization;
use App\Models\SeoLocation;
use App\Services\Seo\NapConsistencyChecker;
use App\Support\CurrentOrganization;

function seoLocation(Organization $org): SeoLocation
{
    app(CurrentOrganization::class)->set($org);
    $location = SeoLocation::create([
        'name' => 'Acme MSP LLC',
        'street' => '1 Main St',
        'city' => 'Dallas',
        'region' => 'TX',
        'postal_code' => '75001',
        'phone' => '(214) 555-1000',
    ]);
    app(CurrentOrganization::class)->forget();

    return $location;
}

it('passes a consistent citation and normalizes cosmetic differences', function () {
    [$org] = makeOrganization();
    $location = seoLocation($org);

    $result = (new NapConsistencyChecker)->check($location, [
        'name' => 'Acme MSP',                 // LLC suffix dropped
        'address' => '1 Main St, Dallas, TX 75001',
        'phone' => '214-555-1000',            // different formatting
    ]);

    expect($result['status'])->toBe('consistent')->and($result['mismatches'])->toBe([]);
});

it('flags an inconsistent citation with the mismatched fields', function () {
    [$org] = makeOrganization();
    $location = seoLocation($org);

    $result = (new NapConsistencyChecker)->check($location, [
        'name' => 'Acme MSP',
        'address' => '99 Other Road',
        'phone' => '214-555-9999',
    ]);

    expect($result['status'])->toBe('inconsistent')
        ->and($result['mismatches'])->toContain('address')
        ->and($result['mismatches'])->toContain('phone')
        ->and($result['mismatches'])->not->toContain('name');
});

it('marks an empty citation as missing', function () {
    [$org] = makeOrganization();
    $location = seoLocation($org);

    $result = (new NapConsistencyChecker)->check($location, ['name' => '', 'address' => '', 'phone' => '']);

    expect($result['status'])->toBe('missing');
});

it('creates a citation with NAP status via the controller', function () {
    [$org, $owner] = makeOrganization();
    $location = seoLocation($org);

    $this->actingAs($owner)->post(route('seo.local.citations.store', $location->id), [
        'source' => 'Yelp',
        'listed_name' => 'Acme MSP',
        'listed_address' => '99 Wrong Ave',
        'listed_phone' => '(214) 555-1000',
    ])->assertRedirect();

    $citation = Citation::withoutGlobalScope('tenant')->firstWhere('source', 'Yelp');
    expect($citation->status)->toBe('inconsistent')
        ->and($citation->mismatches)->toContain('address');
});

it('isolates locations across tenants', function () {
    [, $ownerA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');
    $locationB = seoLocation($orgB);

    $this->actingAs($ownerA)->delete(route('seo.local.destroy', $locationB->id))->assertNotFound();
});
