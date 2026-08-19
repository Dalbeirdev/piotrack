<?php

declare(strict_types=1);

/**
 * QA §66 - Final realistic end-to-end scenario.
 *
 * Business:  Acme Managed IT Services
 * Prospect:  Precision Manufacturing Group - Michael Rodriguez, CFO
 * Campaign:  Philadelphia CMMC Lead Generation
 * Keyword:   CMMC MSP Philadelphia
 * Outcome:   $4,500 MRR / $54,000 ARR attributed to the campaign
 *
 * Every step asserts observed database state, not that a call returned without
 * throwing. Money is stored in minor units throughout, so $4,500 is 450000.
 */

use App\Authorization\Role;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Form;
use App\Models\Pipeline;
use App\Models\ScoringRule;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\AttributionService;
use App\Services\Marketing\LeadCaptureService;
use App\Services\Sales\LeadScoringService;
use App\Support\CurrentOrganization;

const MRR_MINOR = 450_000;   // $4,500.00

const ARR_MINOR = 5_400_000; // $54,000.00

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'professional');
    app(CurrentOrganization::class)->set($this->org);

    $this->sarah = addMember($this->org, Role::SalesManager);
    $this->sarah->forceFill(['name' => 'Sarah Mitchell'])->save();
});

afterEach(function () {
    app(CurrentOrganization::class)->forget();
});

it('carries a Philadelphia CMMC lead from form submission to attributed revenue', function () {
    // -- 1. Landing page form for the campaign -----------------------------
    $form = Form::create([
        'name' => 'Philadelphia CMMC Lead Generation',
        'slug' => 'philadelphia-cmmc-lead-generation',
        'fields' => [
            ['name' => 'first_name', 'type' => 'text', 'required' => true],
            ['name' => 'last_name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'type' => 'email', 'required' => true],
            ['name' => 'company', 'type' => 'text', 'required' => false],
        ],
        'lifecycle_stage' => 'lead',
        'status' => 'published',
    ]);

    expect($form->organization_id)->toBe($this->org->id);

    // -- 2. Michael Rodriguez submits the form -----------------------------
    $contact = app(LeadCaptureService::class)->capture($form, [
        'first_name' => 'Michael',
        'last_name' => 'Rodriguez',
        'email' => 'michael.rodriguez@precisionmfg-test.com',
        'phone' => '+12155550142',
        'company' => 'Precision Manufacturing Group',
    ], '203.0.113.24', 'Mozilla/5.0 QA');

    expect($contact->exists)->toBeTrue()
        ->and($contact->email)->toBe('michael.rodriguez@precisionmfg-test.com')
        ->and($contact->organization_id)->toBe($this->org->id);

    // The submission itself must be recorded, not just the contact.
    expect(DB::table('form_submissions')->where('contact_id', $contact->id)->count())->toBe(1)
        ->and($form->fresh()->submission_count)->toBe(1);

    // -- 3. Company and role, as a rep would fill in after the enquiry -----
    $company = Company::create([
        'name' => 'Precision Manufacturing Group',
        'industry' => 'manufacturing',
        'city' => 'Philadelphia',
        'region' => 'Pennsylvania',
        'country' => 'US',
        'size' => '180',
    ]);

    $contact->update([
        'title' => 'CFO',
        'company_id' => $company->id,
        'lead_source' => 'paid',
        'campaign' => 'Philadelphia CMMC Lead Generation',
        'owner_id' => $this->sarah->id,
    ]);

    // -- 4. Lead scoring ---------------------------------------------------
    foreach ([
        ['CFO title', 'title', 'contains', 'CFO', 20],
        ['Paid acquisition', 'lead_source', 'equals', 'paid', 15],
        ['Opted in', 'email_opt_in', 'is_true', null, 10],
    ] as [$name, $attribute, $operator, $value, $points]) {
        ScoringRule::create([
            'name' => $name, 'category' => 'demographic', 'attribute' => $attribute,
            'operator' => $operator, 'value' => $value, 'points' => $points, 'is_active' => true,
        ]);
    }

    $contact->update(['email_opt_in' => true]);
    $scoring = app(LeadScoringService::class);
    $scored = $scoring->apply($contact->fresh());

    expect($scored->lead_score)->toBe(45)
        ->and($scoring->temperature(45))->toBe('warm');

    // Push past the SQL threshold. `has_company` is one of the six attributes
    // ScoringController allows; a rule on anything else would silently score 0.
    ScoringRule::create([
        'name' => 'Known company', 'category' => 'firmographic', 'attribute' => 'has_company',
        'operator' => 'is_true', 'value' => null, 'points' => 20, 'is_active' => true,
    ]);
    $scored = $scoring->apply($contact->fresh());

    expect($scored->lead_score)->toBe(65)
        ->and($scoring->temperature($scored->lead_score))->toBe('hot')
        ->and($scored->lifecycle_stage)->toBe('sql');

    // -- 5. Opportunity through the pipeline -------------------------------
    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $stages = $pipeline->stages()->orderBy('sort_order')->get();
    $won = $stages->firstWhere('is_won', true);

    expect($won)->not->toBeNull('the default pipeline must have a won stage');

    $deal = Deal::create([
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stages->first()->id,
        'name' => 'Precision Manufacturing - Cybersecurity Managed Services',
        'contact_id' => $contact->id,
        'company_id' => $company->id,
        'value' => ARR_MINOR,
        'mrr' => MRR_MINOR,
        'contract_term_months' => 12,
        'status' => 'open',
        'lead_source' => 'paid',
        'campaign' => 'Philadelphia CMMC Lead Generation',
        'owner_id' => $this->sarah->id,
    ]);

    $deal->update(['stage_id' => $won->id, 'status' => 'won', 'closed_at' => now()]);

    // -- 6. Revenue arithmetic --------------------------------------------
    // §33 requires $4,500 MRR to yield $54,000 ARR without anyone typing it in.
    $deal = $deal->fresh();

    expect($deal->mrr)->toBe(MRR_MINOR)
        ->and($deal->arr)->toBe(ARR_MINOR)
        ->and($deal->status)->toBe('won');

    // -- 7. Attribution ----------------------------------------------------
    $attribution = app(AttributionService::class);
    $byCampaign = $attribution->campaignRevenue();
    $byChannel = $attribution->channelRevenue();

    expect($byCampaign)->toHaveKey('Philadelphia CMMC Lead Generation')
        ->and($byCampaign['Philadelphia CMMC Lead Generation'])->toBe(ARR_MINOR)
        ->and($byChannel)->toHaveKey('paid')
        ->and($byChannel['paid'])->toBe(ARR_MINOR);

    // -- 8. Dashboard must agree with the raw records ----------------------
    $revenue = app(AnalyticsService::class)->revenue();

    expect($revenue['mrr'])->toBe((int) Deal::whereHas('stage', fn ($q) => $q->where('is_won', true))->sum('mrr'))
        ->and($revenue['arr'])->toBe(ARR_MINOR)
        ->and($revenue['contract_value'])->toBe((int) Deal::whereHas('stage', fn ($q) => $q->where('is_won', true))->sum('value'));
});

it('keeps the whole journey inside its own tenant', function () {
    $form = Form::create([
        'name' => 'Philadelphia CMMC Lead Generation',
        'slug' => 'philly-cmmc-isolation',
        'fields' => [], 'lifecycle_stage' => 'lead', 'status' => 'published',
    ]);

    app(LeadCaptureService::class)->capture($form, [
        'first_name' => 'Michael', 'last_name' => 'Rodriguez',
        'email' => 'michael.rodriguez@precisionmfg-test.com',
    ]);

    Deal::create([
        'pipeline_id' => Pipeline::where('is_default', true)->firstOrFail()->id,
        'stage_id' => Pipeline::where('is_default', true)->firstOrFail()->stages()->first()->id,
        'name' => 'Acme deal', 'value' => ARR_MINOR, 'mrr' => MRR_MINOR,
        'status' => 'won', 'lead_source' => 'paid',
        'campaign' => 'Philadelphia CMMC Lead Generation', 'closed_at' => now(),
    ]);

    // A second, unrelated tenant must see none of it.
    app(CurrentOrganization::class)->forget();
    [$northstar] = makeOrganization('Northstar Cybersecurity');
    subscribeOrganization($northstar, 'professional');
    app(CurrentOrganization::class)->set($northstar);

    $revenue = app(AnalyticsService::class)->revenue();

    expect(Contact::count())->toBe(0)
        ->and(Deal::count())->toBe(0)
        ->and(Form::count())->toBe(0)
        ->and(app(AttributionService::class)->campaignRevenue())->toBe([])
        ->and($revenue['mrr'])->toBe(0)
        ->and($revenue['arr'])->toBe(0)
        ->and($revenue['contract_value'])->toBe(0);
});
