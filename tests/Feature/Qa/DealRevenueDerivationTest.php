<?php

declare(strict_types=1);

/**
 * QA §33/§34 - a won deal worth $4,500 MRR must report $54,000 ARR.
 *
 * ARR is a nullable column that DealController accepts as optional, so a deal
 * created through the UI with only MRR filled in used to leave ARR null and the
 * revenue dashboard reported $0 annual recurring revenue against $4,500 monthly.
 */

use App\Models\Deal;
use App\Models\Pipeline;
use App\Services\Analytics\AnalyticsService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'professional');
    app(CurrentOrganization::class)->set($this->org);

    $this->pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $this->wonStage = $this->pipeline->stages()->where('is_won', true)->firstOrFail();
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('derives ARR from MRR when ARR is not supplied', function () {
    $deal = Deal::create([
        'pipeline_id' => $this->pipeline->id,
        'stage_id' => $this->wonStage->id,
        'name' => 'Precision Manufacturing - Cybersecurity Managed Services',
        'value' => 5_400_000,
        'mrr' => 450_000,   // $4,500.00 - ARR deliberately omitted
        'status' => 'won',
        'closed_at' => now(),
    ]);

    expect($deal->fresh()->arr)->toBe(5_400_000);

    $revenue = app(AnalyticsService::class)->revenue();

    expect($revenue['mrr'])->toBe(450_000)
        ->and($revenue['arr'])->toBe(5_400_000);
});

it('respects an ARR that was entered explicitly', function () {
    // A two-year prepaid deal where the operator states ARR themselves.
    $deal = Deal::create([
        'pipeline_id' => $this->pipeline->id,
        'stage_id' => $this->wonStage->id,
        'name' => 'Prepaid multi-year',
        'value' => 9_600_000,
        'mrr' => 400_000,
        'arr' => 4_000_000,
        'status' => 'won',
    ]);

    expect($deal->fresh()->arr)->toBe(4_000_000);
});

it('leaves ARR at zero for a one-off deal with no MRR', function () {
    $deal = Deal::create([
        'pipeline_id' => $this->pipeline->id,
        'stage_id' => $this->wonStage->id,
        'name' => 'One-off project',
        'value' => 1_200_000,
        'status' => 'won',
    ]);

    expect($deal->fresh()->arr)->toBe(0);
});

it('recomputes ARR when MRR changes and ARR was derived', function () {
    $deal = Deal::create([
        'pipeline_id' => $this->pipeline->id,
        'stage_id' => $this->pipeline->stages()->first()->id,
        'name' => 'Expanding account',
        'value' => 5_400_000,
        'mrr' => 450_000,
    ]);

    expect($deal->fresh()->arr)->toBe(5_400_000);

    $deal->update(['mrr' => 600_000]);

    expect($deal->fresh()->arr)->toBe(7_200_000);
});
