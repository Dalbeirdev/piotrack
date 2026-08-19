<?php

declare(strict_types=1);

/**
 * QA §56 - performance, the part that is meaningful in a test environment.
 *
 * Wall-clock timing against an in-memory sqlite database on CI hardware proves
 * nothing about production latency, so this does not pretend to. It targets the
 * one performance defect that IS deterministic regardless of hardware and is by
 * far the most damaging at scale: the N+1 query, where the number of queries
 * grows with the number of rows. It is invisible with seed data and quietly
 * fatal in production.
 *
 * The method: exercise a surface with N records, then with 3N, counting queries
 * both times. A constant count means the work is set-based; a count that climbs
 * with the data is an N+1.
 */

use App\Models\Company;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\Platform\PlatformAdminService;
use App\Services\SubscriptionService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\DB;

/** Run a callable and return how many queries it issued. */
function queryCount(callable $fn): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $fn();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('lists contacts with a query count that does not grow with the rows', function () {
    app(CurrentOrganization::class)->set($this->org);
    $company = Company::create(['name' => 'Precision Manufacturing Group', 'industry' => 'manufacturing']);
    $seed = function (int $n) use ($company) {
        app(CurrentOrganization::class)->set($this->org);
        foreach (range(1, $n) as $i) {
            Contact::create([
                'first_name' => 'Prospect', 'last_name' => (string) $i,
                'email' => 'p'.$i.'-'.uniqid().'@precisionmfg-test.com',
                'company_id' => $company->id, 'owner_id' => $this->owner->id,
            ]);
        }
        app(CurrentOrganization::class)->forget();
    };
    app(CurrentOrganization::class)->forget();

    $seed(5);
    $few = queryCount(fn () => $this->actingAs($this->owner)->get(route('crm.contacts.index'))->assertSuccessful());

    $seed(20);
    $many = queryCount(fn () => $this->actingAs($this->owner)->get(route('crm.contacts.index'))->assertSuccessful());

    // Pagination caps the page at 20 and company/owner are eager-loaded, so the
    // count is flat: it varies by at most a query of incidental middleware
    // jitter, never the ~15 an N+1 would add for the extra rows.
    expect($many)->toBeLessThanOrEqual($few + 2);
});

it('lists platform tenants with a flat query count as tenants multiply', function () {
    // This is the surface the N+1 was on: tenants() called activeSubscription()
    // and then read ->plan for every organization.
    $makeTenants = function (int $n) {
        foreach (range(1, $n) as $i) {
            [$org, $owner] = makeOrganization('Tenant '.uniqid());
            $plan = Plan::where('code', 'professional')->firstOrFail();
            app(SubscriptionService::class)->checkout($org, $plan, 'monthly', 1, null);
        }
        app(CurrentOrganization::class)->forget();
    };

    $makeTenants(2);
    $few = queryCount(fn () => app(PlatformAdminService::class)->tenants());

    $makeTenants(6);
    $many = queryCount(fn () => app(PlatformAdminService::class)->tenants());

    // With the fix the two counts are equal; before it, `many` grew by roughly
    // two queries per added tenant. Allow no growth.
    expect($many)->toBe($few);

    // And the data is still correct - the fix must not have dropped the plan.
    $rows = app(PlatformAdminService::class)->tenants();
    expect(collect($rows)->pluck('plan')->filter()->count())->toBeGreaterThan(0);
});

it('bounds the platform overview to a constant number of queries', function () {
    // overview() is all aggregates; adding tenants must not add queries.
    foreach (range(1, 3) as $i) {
        [$org] = makeOrganization('Extra '.uniqid());
        subscribeOrganization($org, 'growth');
    }
    app(CurrentOrganization::class)->forget();

    $before = queryCount(fn () => app(PlatformAdminService::class)->overview());

    foreach (range(1, 5) as $i) {
        [$org] = makeOrganization('More '.uniqid());
        subscribeOrganization($org, 'growth');
    }
    app(CurrentOrganization::class)->forget();

    $after = queryCount(fn () => app(PlatformAdminService::class)->overview());

    expect($after)->toBe($before);
});

it('streams the contact export with a query count that does not grow with the rows', function () {
    $seed = function (int $n) {
        app(CurrentOrganization::class)->set($this->org);
        foreach (range(1, $n) as $i) {
            Contact::create(['first_name' => 'P'.$i, 'email' => 'p'.$i.'-'.uniqid().'@precisionmfg-test.com']);
        }
        app(CurrentOrganization::class)->forget();
    };

    $export = fn () => $this->actingAs($this->owner)->get(route('crm.contacts.export'))->streamedContent();

    $seed(10);
    $few = queryCount($export);

    $seed(30);
    $many = queryCount($export);

    // The export chunks at 500 and eager-loads company, so both runs (40 rows
    // fit one chunk) issue essentially the same number of queries. A per-row
    // pattern would make `many` climb by ~20; incidental jitter is at most one.
    expect($many)->toBeLessThanOrEqual($few + 2);
});

it('keeps organization_id indexed on the large hot tables', function () {
    // Every tenant-scoped query filters on organization_id, so it must be
    // indexed on the tables that grow. A missing index turns every list into a
    // full scan at production volume.
    $hot = ['contacts', 'deals', 'activities', 'outbound_messages', 'audit_logs', 'keyword_rankings'];

    $missing = [];
    foreach ($hot as $table) {
        $indexes = collect(Schema::getIndexes($table));
        $covered = $indexes->contains(fn ($index) => in_array('organization_id', $index['columns'], true));
        if (! $covered) {
            $missing[] = $table;
        }
    }

    expect($missing)->toBe([]);
});
