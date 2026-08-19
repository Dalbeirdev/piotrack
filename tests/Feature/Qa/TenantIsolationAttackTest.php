<?php

declare(strict_types=1);

/**
 * QA §12 - Multi-tenant isolation, attacked rather than assumed.
 *
 * Organization A: Acme Managed IT Services
 * Organization B: Northstar Cybersecurity
 *
 * Existing tenancy tests assert that queries are scoped. This one authenticates
 * as Acme and actively goes after Northstar's records through every vector §12
 * names: URL manipulation, request-body foreign-key injection, API id
 * manipulation, and global search.
 *
 * The audit that motivated it: many controllers validate foreign keys with an
 * unscoped `exists` rule, and Laravel's exists rule runs a raw query that the
 * Eloquent global tenant scope never sees. Every one of those is a candidate
 * cross-tenant reference, so each is exercised against a live route.
 *
 * Any failure here is P0.
 */

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\MarketingList;
use App\Models\Pipeline;
use App\Support\CurrentOrganization;

beforeEach(function () {
    // -- Organization B: the victim ---------------------------------------
    [$this->northstar, $this->northstarOwner] = makeOrganization('Northstar Cybersecurity');
    subscribeOrganization($this->northstar, 'professional');
    app(CurrentOrganization::class)->set($this->northstar);

    $this->victimCompany = Company::create([
        'name' => 'Northstar Confidential Client', 'industry' => 'defense',
        'city' => 'Cherry Hill', 'region' => 'New Jersey', 'country' => 'US',
    ]);

    $this->victimContact = Contact::create([
        'first_name' => 'Helena', 'last_name' => 'Vasquez',
        'email' => 'helena.vasquez@northstar-victim-test.com',
        'title' => 'CISO', 'company_id' => $this->victimCompany->id,
        'lead_source' => 'referral', 'lifecycle_stage' => 'customer',
    ]);

    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $this->victimDeal = Deal::create([
        'pipeline_id' => $pipeline->id,
        'stage_id' => $pipeline->stages()->first()->id,
        'name' => 'Northstar - classified engagement',
        'contact_id' => $this->victimContact->id,
        'value' => 9_900_000, 'mrr' => 825_000, 'status' => 'open',
    ]);

    $this->victimList = MarketingList::create([
        'name' => 'Northstar private prospect list', 'type' => 'static',
    ]);

    app(CurrentOrganization::class)->forget();

    // -- Organization A: the attacker -------------------------------------
    [$this->acme, $this->acmeOwner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->acme, 'professional');
    $this->attacker = $this->acmeOwner;
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

/*
|--------------------------------------------------------------------------
| Control - the victim's data really exists and really is reachable
|--------------------------------------------------------------------------
|
| Without this, every "access denied" below could be passing because the
| records were never created, and the suite would prove nothing at all.
*/

it('CONTROL: Northstar can see its own records, and Acme can see its own', function () {
    $this->actingAs($this->northstarOwner)
        ->get(route('crm.contacts.show', $this->victimContact->id))
        ->assertSuccessful();

    $this->actingAs($this->northstarOwner)
        ->get(route('crm.deals.show', $this->victimDeal->id))
        ->assertSuccessful();

    // And the victim is findable at all, outside any tenant scope.
    expect(Contact::withoutGlobalScope('tenant')->find($this->victimContact->id))->not->toBeNull();

    // Acme's own search and export must return real content, so that the
    // "absent" assertions later are about isolation rather than empty responses.
    app(CurrentOrganization::class)->set($this->acme);
    Contact::create([
        'first_name' => 'Michael', 'last_name' => 'Rodriguez',
        'email' => 'michael.rodriguez@precisionmfg-test.com', 'title' => 'CFO',
    ]);
    app(CurrentOrganization::class)->forget();

    $search = $this->actingAs($this->attacker)->get('/search?q=Michael');
    expect($search->getContent())->toContain('Michael');

    $export = $this->actingAs($this->attacker)->get(route('crm.contacts.export'));
    expect($export->streamedContent())->toContain('michael.rodriguez@precisionmfg-test.com');
});

/*
|--------------------------------------------------------------------------
| Vector 1 - URL manipulation (route-model binding)
|--------------------------------------------------------------------------
*/

it('refuses URL access to another tenant CRM records', function () {
    $routes = [
        route('crm.contacts.show', $this->victimContact->id),
        route('crm.deals.show', $this->victimDeal->id),
        route('marketing.lists.show', $this->victimList->id),
    ];

    foreach ($routes as $url) {
        $this->actingAs($this->attacker)->get($url)->assertNotFound();
    }
});

it('refuses to mutate another tenant records by URL', function () {
    $this->actingAs($this->attacker)
        ->patch(route('crm.contacts.update', $this->victimContact->id), ['first_name' => 'Pwned'])
        ->assertNotFound();

    $this->actingAs($this->attacker)
        ->delete(route('crm.contacts.destroy', $this->victimContact->id))
        ->assertNotFound();

    expect($this->victimContact->fresh()->first_name)->toBe('Helena')
        ->and(Contact::withoutGlobalScope('tenant')->find($this->victimContact->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Vector 2 - request-body foreign-key injection
|--------------------------------------------------------------------------
|
| Each of these posts Northstar's contact id into an Acme-owned endpoint whose
| validation rule does not scope by organization.
*/

it('refuses a foreign contact id posted to the AI agent', function () {
    $response = $this->actingAs($this->attacker)->post(route('ai.agent.run'), [
        'contact_id' => $this->victimContact->id,
        'task' => 'research',
    ]);

    // Must not return the victim's details in an AI result.
    expect($response->status())->not->toBe(200);
    expect(session('ai_result'))->toBeNull();
});

it('refuses a foreign contact id added to a marketing list', function () {
    app(CurrentOrganization::class)->set($this->acme);
    $ownList = MarketingList::create(['name' => 'Acme prospects', 'type' => 'static']);
    app(CurrentOrganization::class)->forget();

    // Rejected at validation now that the rule is tenant-scoped; before the fix
    // it reached Contact::findOrFail and 404ed. Either way it must not succeed.
    $this->actingAs($this->attacker)
        ->post(route('marketing.lists.contacts.add', $ownList->id), [
            'contact_id' => $this->victimContact->id,
        ])
        ->assertSessionHasErrors('contact_id');

    // The victim must not have been enrolled into the attacker's list.
    app(CurrentOrganization::class)->set($this->acme);
    expect($ownList->fresh()->contacts()->count())->toBe(0);
});

it('refuses a foreign contact id posted to intent recording', function () {
    $response = $this->actingAs($this->attacker)->post(route('sales.intent.store'), [
        'contact_id' => $this->victimContact->id,
        'signal' => 'pricing_page',
    ]);

    expect($response->status())->not->toBe(200);

    // Whatever the status, nothing may have been recorded against the victim.
    foreach (['intent_signals', 'intent_events'] as $table) {
        if (Schema::hasTable($table)) {
            expect(DB::table($table)->where('contact_id', $this->victimContact->id)->count())->toBe(0);
        }
    }
});

it('refuses a foreign contact id when creating a deal', function () {
    app(CurrentOrganization::class)->set($this->acme);
    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->attacker)
        ->post(route('crm.deals.store'), [
            'name' => 'Injected deal',
            'pipeline_id' => $pipeline->id,
            'contact_id' => $this->victimContact->id,
            'value' => 1000,
        ])
        ->assertSessionHasErrors('contact_id');

    expect(Deal::withoutGlobalScope('tenant')->where('name', 'Injected deal')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Vector 3 - API id manipulation
|--------------------------------------------------------------------------
*/

it('refuses API access to another tenant contact by id', function () {
    $token = $this->attacker->createToken('qa')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/contacts/'.$this->victimContact->id)
        ->assertNotFound();
});

it('refuses an X-Organization-Id header for an organization you do not belong to', function () {
    $token = $this->attacker->createToken('qa')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->withHeader('X-Organization-Id', (string) $this->northstar->id)
        ->getJson('/api/v1/contacts');

    // SetApiOrganization answers 400 for a non-member; what matters is that the
    // request is refused and carries none of the victim's data back.
    expect($response->status())->toBeGreaterThanOrEqual(400)
        ->and($response->status())->toBeLessThan(500)
        ->and($response->json('message'))->toContain('not a member');

    expect(json_encode($response->json()))
        ->not->toContain('Helena')
        ->not->toContain('northstar-victim-test');
});

it('never lists another tenant contacts through the API', function () {
    $token = $this->attacker->createToken('qa')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/contacts')
        ->assertSuccessful();

    expect($response->json())->not->toContain('helena.vasquez@northstar-victim-test.com');
    expect(json_encode($response->json()))->not->toContain('Helena');
});

/*
|--------------------------------------------------------------------------
| Vector 4 - search and export
|--------------------------------------------------------------------------
*/

it('never surfaces another tenant records in global search', function () {
    foreach (['Helena', 'Vasquez', 'northstar-victim-test', 'Northstar Confidential'] as $term) {
        $response = $this->actingAs($this->attacker)->get('/search?q='.urlencode($term));
        $response->assertSuccessful();

        expect($response->getContent())->not->toContain('helena.vasquez@northstar-victim-test.com')
            ->and($response->getContent())->not->toContain('Northstar Confidential Client');
    }
});

it('never includes another tenant records in an export', function () {
    $response = $this->actingAs($this->attacker)->get(route('crm.contacts.export'));
    $response->assertSuccessful();

    $body = $response->streamedContent();

    expect($body)->not->toContain('Helena')
        ->and($body)->not->toContain('helena.vasquez@northstar-victim-test.com');
});
