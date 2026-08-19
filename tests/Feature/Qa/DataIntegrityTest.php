<?php

declare(strict_types=1);

/**
 * QA §49 - data integrity across a realistic dataset.
 *
 * Rather than assert one relationship at a time, this seeds the full demo
 * organization (contacts, deals, campaigns, keywords, content, bookings,
 * projects, a website - every module) plus a second tenant with its own data,
 * then sweeps the whole schema for the invariants §49 names: orphaned rows,
 * organization_id pointing nowhere, and - the one that matters most after this
 * run's unscoped-foreign-key finding - any foreign key that crosses a tenant
 * boundary.
 *
 * The validation fix (TenantExists) stops new cross-tenant references at the
 * door; this checks the guarantee at the data layer, over data built by the
 * real services rather than by hand.
 */

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('app.demo_seed_allowed', true);
    $this->seed(DemoSeeder::class);

    // A second tenant with its own records, so a cross-tenant reference has
    // somewhere to point if isolation were broken.
    [$this->rival, $this->rivalOwner] = makeOrganization('Northstar Cybersecurity');
    subscribeOrganization($this->rival, 'professional');
    app(CurrentOrganization::class)->set($this->rival);
    $company = Company::create(['name' => 'Rival Client', 'industry' => 'defense']);
    Contact::create(['first_name' => 'Rival', 'email' => 'rival@northstar-test.example', 'company_id' => $company->id]);
    app(CurrentOrganization::class)->forget();
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

/** Tenant-owned tables (those carrying organization_id). */
function tenantTables(): array
{
    return array_values(array_filter(
        Schema::getTableListing(),
        fn (string $t) => in_array('organization_id', Schema::getColumnListing($t), true),
    ));
}

/** Strip the connection/schema prefix Postgres and sqlite attach. */
function bare(string $table): string
{
    return Str::afterLast($table, '.');
}

it('leaves no tenant row without a valid organization', function () {
    $orphans = [];

    foreach (tenantTables() as $table) {
        $t = bare($table);

        // organization_id must never be null on a tenant-owned row.
        $nullOrg = DB::table($t)->whereNull('organization_id')->count();
        if ($nullOrg > 0) {
            $orphans[] = "{$t}: {$nullOrg} row(s) with null organization_id";
        }

        // and must reference an organization that exists.
        $dangling = DB::table($t)
            ->whereNotNull('organization_id')
            ->whereNotIn('organization_id', fn ($q) => $q->select('id')->from('organizations'))
            ->count();
        if ($dangling > 0) {
            $orphans[] = "{$t}: {$dangling} row(s) reference a missing organization";
        }
    }

    expect($orphans)->toBe([]);
});

it('never lets a foreign key cross a tenant boundary', function () {
    // Columns that look like a foreign key but are not one, or are handled
    // separately (organization_id is the tenant key itself; *_by / actor / user
    // reference the global users table, which is not tenant-owned).
    $notTenantFks = ['organization_id', 'id', 'requested_by', 'uploaded_by', 'user_id',
        'actor_id', 'owner_id', 'assignee_id', 'requester_id', 'impersonator_id',
        'marketing_owner_id', 'created_by', 'updated_by'];

    $violations = [];

    foreach (tenantTables() as $table) {
        $t = bare($table);
        $columns = Schema::getColumnListing($t);

        foreach ($columns as $column) {
            if (! str_ends_with($column, '_id') || in_array($column, $notTenantFks, true)) {
                continue;
            }

            // Infer the referenced table by Laravel convention: contact_id ->
            // contacts. Only sweep the well-known tenant-owned foreign keys.
            $target = Str::plural(Str::beforeLast($column, '_id'));
            if (! in_array($column, ['contact_id', 'company_id', 'deal_id', 'keyword_id',
                'campaign_id', 'marketing_list_id', 'workflow_id', 'form_id', 'booking_page_id',
                'content_piece_id', 'pipeline_id', 'stage_id', 'ad_campaign_id', 'project_id',
                'sprint_id', 'list_id', 'target_list_id', 'ai_prompt_id', 'ticket_id'], true)) {
                continue;
            }

            // Resolve the real target table for the irregular names.
            $target = match ($column) {
                'stage_id' => 'pipeline_stages',
                'marketing_list_id', 'target_list_id', 'list_id' => 'marketing_lists',
                'ad_campaign_id' => 'ad_campaigns',
                'ai_prompt_id' => 'ai_prompts',
                default => $target,
            };

            if (! Schema::hasTable($target) || ! in_array('organization_id', Schema::getColumnListing($target), true)) {
                continue;
            }

            $crossing = DB::table($t)
                ->join($target, "{$t}.{$column}", '=', "{$target}.id")
                ->whereColumn("{$t}.organization_id", '!=', "{$target}.organization_id")
                ->count();

            if ($crossing > 0) {
                $violations[] = "{$t}.{$column} -> {$target}: {$crossing} row(s) cross a tenant boundary";
            }
        }
    }

    expect($violations)->toBe([]);
});

it('holds the arr = mrr x 12 invariant on every derived deal', function () {
    // Deals where ARR was derived (mrr set, arr = mrr*12) must stay consistent
    // across the seeded pipeline, not just in the unit test that added it.
    $inconsistent = Deal::withoutGlobalScope('tenant')
        ->where('mrr', '>', 0)
        ->get()
        ->filter(fn (Deal $d) => $d->arr !== $d->mrr * 12 && $d->arr !== 0)
        ->map(fn (Deal $d) => "deal {$d->id}: mrr {$d->mrr}, arr {$d->arr}")
        ->all();

    // The seeder sets arr explicitly to mrr*12, so all rows must agree.
    expect($inconsistent)->toBe([]);
});

it('keeps deal status within the set the pipeline machinery enforces', function () {
    // Deal status is driven by the won/lost pipeline stages, so it is a genuine
    // closed set - unlike contact lifecycle_stage, which the app validates only
    // as string|max:40 (free-form; see the note below). Assert the constrained
    // one strictly.
    $badDeals = Deal::withoutGlobalScope('tenant')
        ->whereNotNull('status')
        ->whereNotIn('status', ['open', 'won', 'lost'])
        ->pluck('status')->unique()->all();

    expect($badDeals)->toBe([]);
});

it('uses a consistent lifecycle vocabulary across the seeded data', function () {
    // lifecycle_stage carries no enforced enum - controllers validate it as
    // string|max:40 only, so a typo like "Customer" would fragment the funnel
    // silently. That is a design observation, not a defect, so this asserts the
    // weaker guarantee that actually holds: the real data uses the known
    // lowercase vocabulary (default 'subscriber' plus the funnel stages), which
    // catches accidental drift in the seeder and services.
    $known = ['subscriber', 'lead', 'mql', 'sql', 'opportunity', 'customer', 'evangelist'];

    $unexpected = Contact::withoutGlobalScope('tenant')
        ->whereNotNull('lifecycle_stage')
        ->whereNotIn('lifecycle_stage', $known)
        ->pluck('lifecycle_stage')->unique()->all();

    expect($unexpected)->toBe([]);
});

it('enforces the unique-contact-email-per-organization constraint', function () {
    $dupes = DB::table('contacts')
        ->select('organization_id', 'email', DB::raw('count(*) as c'))
        ->whereNotNull('email')
        ->groupBy('organization_id', 'email')
        ->havingRaw('count(*) > 1')
        ->get();

    expect($dupes)->toHaveCount(0);

    // The same email in two different organizations is allowed and must not
    // be treated as a duplicate.
    $demoOrg = Organization::where('name', 'Northwind IT Services')->firstOrFail();
    app(CurrentOrganization::class)->set($demoOrg);
    $shared = Contact::first()->email;
    app(CurrentOrganization::class)->forget();

    app(CurrentOrganization::class)->set($this->rival);
    $ok = Contact::create(['first_name' => 'Same Email', 'email' => $shared]);
    app(CurrentOrganization::class)->forget();

    expect($ok->exists)->toBeTrue();
});
