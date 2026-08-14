<?php

use App\Authorization\Role;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Contact;
use App\Models\KpiTarget;
use App\Models\PerformanceAgreement;
use App\Models\StrategyItem;
use App\Models\StrategyPlan;
use App\Services\Strategy\KpiTargetService;
use App\Services\Strategy\MethodologyService;
use App\Services\Strategy\PerformanceService;
use App\Support\CurrentOrganization;

/** Seed a small funnel: $leads contacts of which $sqls are SQL, plus $meetings bookings. */
function seedDeliveryFunnel(int $leads, int $sqls, int $meetings = 0): void
{
    for ($i = 0; $i < $leads; $i++) {
        Contact::create([
            'first_name' => 'L'.$i,
            'email' => "lead{$i}-".uniqid().'@x.com',
            'lifecycle_stage' => $i < $sqls ? 'sql' : 'lead',
        ]);
    }

    if ($meetings > 0) {
        $page = BookingPage::create([
            'name' => 'Consult', 'slug' => 'perf-'.uniqid(), 'meeting_type' => 'consultation',
            'duration_minutes' => 30, 'assignment' => 'fixed', 'is_active' => true,
        ]);

        for ($i = 0; $i < $meetings; $i++) {
            Booking::create([
                'booking_page_id' => $page->id, 'name' => 'M'.$i, 'email' => "m{$i}@x.com",
                'scheduled_at' => now()->addDay(), 'status' => 'booked',
            ]);
        }
    }
}

it('measures KPI targets against real funnel actuals', function () {
    [$org] = deliveryOrganization();
    app(CurrentOrganization::class)->set($org);
    seedDeliveryFunnel(leads: 10, sqls: 4);

    KpiTarget::create(['metric' => 'leads', 'target_value' => 8, 'period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth()]);
    KpiTarget::create(['metric' => 'sqls', 'target_value' => 10, 'period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth()]);

    $rows = collect(app(KpiTargetService::class)->attainment());
    app(CurrentOrganization::class)->forget();

    $leads = $rows->firstWhere('metric', 'leads');
    $sqls = $rows->firstWhere('metric', 'sqls');

    expect($leads['actual'])->toBe(10)->and($leads['attainment'])->toBe(125.0)->and($leads['on_track'])->toBeTrue()
        ->and($sqls['actual'])->toBe(4)->and($sqls['on_track'])->toBeFalse();
});

it('treats a lower-is-better metric correctly', function () {
    [$org] = deliveryOrganization();
    app(CurrentOrganization::class)->set($org);
    seedDeliveryFunnel(leads: 5, sqls: 1);

    KpiTarget::create(['metric' => 'cpl', 'target_value' => 5000, 'period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth()]);
    $row = collect(app(KpiTargetService::class)->attainment())->firstWhere('metric', 'cpl');
    app(CurrentOrganization::class)->forget();

    // No ad spend yet, so CPL is 0 — not "on track", because there is nothing to measure.
    expect($row['lower_is_better'])->toBeTrue()->and($row['actual'])->toBe(0)->and($row['on_track'])->toBeFalse();
});

it('computes performance attainment net of replaced leads', function () {
    [$org] = deliveryOrganization();
    app(CurrentOrganization::class)->set($org);
    seedDeliveryFunnel(leads: 10, sqls: 5, meetings: 3);

    $service = app(PerformanceService::class);
    $agreement = $service->create([
        'name' => 'Q3 guarantee', 'model' => 'guarantee',
        'lead_target' => 8, 'sql_target' => 5, 'meeting_target' => 2,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->addMonth()->toDateString(),
    ]);

    $before = $service->attainment($agreement);
    expect($before['targets']['leads']['actual'])->toBe(10)
        ->and($before['all_targets_met'])->toBeTrue()
        ->and($before['status'])->toBe('in_progress');

    // Replace three leads that failed the quality bar.
    foreach (Contact::limit(3)->get() as $contact) {
        $service->replaceLead($agreement, $contact, 'Out of service area');
    }

    $after = $service->attainment($agreement);
    app(CurrentOrganization::class)->forget();

    expect($after['replaced_leads'])->toBe(3)
        ->and($after['targets']['leads']['actual'])->toBe(7)   // 10 - 3
        ->and($after['targets']['leads']['met'])->toBeFalse()  // below the 8 promised
        ->and($after['all_targets_met'])->toBeFalse();
});

it('marks an expired unmet agreement as breached', function () {
    [$org] = deliveryOrganization();
    app(CurrentOrganization::class)->set($org);
    seedDeliveryFunnel(leads: 1, sqls: 0);

    $agreement = app(PerformanceService::class)->create([
        'name' => 'Lapsed', 'model' => 'guarantee', 'lead_target' => 50,
        'period_start' => now()->subMonths(2)->toDateString(),
        'period_end' => now()->subDay()->toDateString(),
    ]);

    $attainment = app(PerformanceService::class)->attainment($agreement);
    app(CurrentOrganization::class)->forget();

    expect($attainment['status'])->toBe('breached');
});

it('evaluates lead quality criteria', function () {
    [$org] = deliveryOrganization();
    app(CurrentOrganization::class)->set($org);
    $service = app(PerformanceService::class);

    $agreement = $service->create([
        'name' => 'Quality bar', 'model' => 'pay_per_lead',
        'quality_criteria' => ['min_lead_score' => 40, 'has_phone' => true],
        'period_start' => now()->toDateString(), 'period_end' => now()->addMonth()->toDateString(),
    ]);

    $good = Contact::create(['first_name' => 'Good', 'email' => 'good@x.com', 'lead_score' => 60, 'phone' => '+15550001']);
    $lowScore = Contact::create(['first_name' => 'Low', 'email' => 'low@x.com', 'lead_score' => 10, 'phone' => '+15550002']);
    $noPhone = Contact::create(['first_name' => 'NoPhone', 'email' => 'np@x.com', 'lead_score' => 90]);

    expect($service->meetsQualityCriteria($agreement, $good))->toBeTrue()
        ->and($service->meetsQualityCriteria($agreement, $lowScore))->toBeFalse()
        ->and($service->meetsQualityCriteria($agreement, $noPhone))->toBeFalse();
    app(CurrentOrganization::class)->forget();
});

it('scores the five-P methodology from real module data with evidence', function () {
    [$org] = deliveryOrganization();
    app(CurrentOrganization::class)->set($org);

    $stages = collect(app(MethodologyService::class)->assess());
    $empty = app(MethodologyService::class)->overall();

    // Nothing set up yet: every stage scores 0 and says so rather than guessing.
    expect($stages)->toHaveCount(5)
        ->and($stages->pluck('stage')->all())->toBe(['position', 'presence', 'pipeline', 'pursuit', 'profit'])
        ->and($empty)->toBe(0)
        ->and($stages->firstWhere('stage', 'position')['evidence'])->not->toBeEmpty();

    // Add real pipeline data and the Pipeline stage moves.
    seedDeliveryFunnel(leads: 4, sqls: 2);
    $after = collect(app(MethodologyService::class)->assess())->firstWhere('stage', 'pipeline');
    app(CurrentOrganization::class)->forget();

    expect($after['score'])->toBeGreaterThan(0)
        ->and(collect($after['evidence'])->firstWhere('signal', 'Contacts in CRM')['met'])->toBeTrue();
});

it('records strategy work with a cross-reference to the module that computes it', function () {
    [$org, $owner] = deliveryOrganization();

    $this->actingAs($owner)->post(route('strategy.plans.store'), [
        'name' => 'FY26 growth plan', 'summary' => 'Position, presence, pipeline',
    ])->assertRedirect();

    $plan = StrategyPlan::withoutGlobalScope('tenant')->firstWhere('name', 'FY26 growth plan');

    $this->actingAs($owner)->post(route('strategy.items.store'), [
        'strategy_plan_id' => $plan->id,
        'type' => 'audit',
        'title' => 'Technical SEO audit',
        'source_module' => 'TSEO',
        'priority' => 'high',
    ])->assertRedirect();

    $item = StrategyItem::withoutGlobalScope('tenant')->firstWhere('title', 'Technical SEO audit');
    expect($item->source_module)->toBe('TSEO')->and($item->strategy_plan_id)->toBe($plan->id);
});

it('stops a viewer from editing the strategy workspace', function () {
    [$org] = deliveryOrganization();
    $viewer = addMember($org, Role::Viewer);

    $this->actingAs($viewer)->get(route('strategy.index'))->assertOk();
    $this->actingAs($viewer)->post(route('strategy.plans.store'), ['name' => 'Nope'])->assertForbidden();
});

it('isolates performance agreements across tenants', function () {
    [, $ownerA] = deliveryOrganization('Tenant A');
    [$orgB] = deliveryOrganization('Tenant B');

    app(CurrentOrganization::class)->set($orgB);
    $agreementB = app(PerformanceService::class)->create([
        'name' => 'B agreement', 'model' => 'guarantee',
        'period_start' => now()->toDateString(), 'period_end' => now()->addMonth()->toDateString(),
    ]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($ownerA)->delete(route('strategy.performance.destroy', $agreementB->id))->assertNotFound();
    expect(PerformanceAgreement::withoutGlobalScope('tenant')->whereKey($agreementB->id)->exists())->toBeTrue();
});
