<?php

use App\Models\Call;
use App\Models\CallTrackingNumber;
use App\Models\Experiment;
use App\Services\Analytics\CallTrackingService;
use App\Services\Analytics\ExperimentService;
use App\Support\CurrentOrganization;

it('provisions a tracking number through the fixture provider', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);

    $number = app(CallTrackingService::class)->provisionNumber(['source' => 'google_ads', 'campaign' => 'spring', 'label' => 'Ads line']);
    app(CurrentOrganization::class)->forget();

    expect($number->phone_number)->toStartWith('+1888')
        ->and($number->source)->toBe('google_ads')
        ->and($number->provider)->toBe('fixture')
        ->and($number->organization_id)->toBe($org->id);
});

it('inherits source attribution from the tracking number and scores the call', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);

    $number = app(CallTrackingService::class)->provisionNumber(['source' => 'organic', 'campaign' => 'brand']);
    $call = app(CallTrackingService::class)->logCall([
        'call_tracking_number_id' => $number->id,
        'from_number' => '+15551234567',
        'duration_seconds' => 300,
        'status' => 'completed',
    ]);
    app(CurrentOrganization::class)->forget();

    expect($call->source)->toBe('organic')          // inherited
        ->and($call->campaign)->toBe('brand')       // inherited
        ->and($call->is_qualified)->toBeTrue()      // 300s >= 120s
        ->and($call->score)->toBe(40);              // floor(300/7.5)
});

it('scores a missed call zero and a converted call higher', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);
    $service = app(CallTrackingService::class);

    $missed = $service->logCall(['duration_seconds' => 0, 'status' => 'missed']);
    expect($missed->score)->toBe(0)->and($missed->is_qualified)->toBeFalse();

    $converted = $service->logCall(['duration_seconds' => 150, 'status' => 'completed']);
    $before = $converted->score;
    $after = $service->markConverted($converted);

    expect($after->converted)->toBeTrue()->and($after->score)->toBe($before + 20);
    app(CurrentOrganization::class)->forget();
});

it('logs a call and provisions a number through the controllers', function () {
    [$org, $owner] = analyticsOrganization();

    $this->actingAs($owner)->post(route('analytics.calls.numbers.store'), ['source' => 'referral'])->assertRedirect();
    $number = CallTrackingNumber::withoutGlobalScope('tenant')->firstWhere('source', 'referral');
    expect($number)->not->toBeNull();

    $this->actingAs($owner)->post(route('analytics.calls.store'), [
        'call_tracking_number_id' => $number->id,
        'direction' => 'inbound',
        'duration_seconds' => 90,
        'status' => 'completed',
    ])->assertRedirect();

    expect(Call::withoutGlobalScope('tenant')->where('source', 'referral')->exists())->toBeTrue();
});

it('computes experiment conversion rate, lift and the winner', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);
    $service = app(ExperimentService::class);

    $experiment = $service->create(
        ['name' => 'Hero headline', 'type' => 'headline'],
        [['name' => 'Control', 'is_control' => true], ['name' => 'Variant B']],
    );
    $control = $experiment->variants->firstWhere('is_control', true);
    $variant = $experiment->variants->firstWhere('is_control', false);

    $service->record($control, 1000, 50);   // 5%
    $service->record($variant, 1000, 75);   // 7.5% -> +50% lift

    $results = collect($service->results($experiment->refresh()));
    $leader = $service->leader($experiment);
    app(CurrentOrganization::class)->forget();

    expect($results->firstWhere('is_control', true)['conversion_rate'])->toBe(5.0)
        ->and($results->firstWhere('is_control', false)['conversion_rate'])->toBe(7.5)
        ->and($results->firstWhere('is_control', false)['lift'])->toBe(50.0)
        ->and($leader->id)->toBe($variant->id);
});

it('concludes an experiment stamping the winning variant', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);
    $service = app(ExperimentService::class);

    $experiment = $service->create(['name' => 'CTA test', 'type' => 'cta'], [['name' => 'A'], ['name' => 'B']]);
    $winner = $experiment->variants->last();
    $service->record($experiment->variants->first(), 100, 1);
    $service->record($winner, 100, 40);
    $concluded = $service->conclude($experiment);
    app(CurrentOrganization::class)->forget();

    expect($concluded->status)->toBe('completed')
        ->and($concluded->winning_variant_id)->toBe($winner->id)
        ->and($concluded->ended_at)->not->toBeNull();
});

it('rejects more conversions than impressions', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);
    $service = app(ExperimentService::class);
    $experiment = $service->create(['name' => 'Bad', 'type' => 'copy'], [['name' => 'A'], ['name' => 'B']]);

    expect(fn () => $service->record($experiment->variants->first(), 10, 20))
        ->toThrow(RuntimeException::class);
    app(CurrentOrganization::class)->forget();
});

it('reports zero lift when the control has no impressions', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);
    $service = app(ExperimentService::class);
    $experiment = $service->create(['name' => 'Empty', 'type' => 'form'], [['name' => 'A'], ['name' => 'B']]);

    $results = $service->results($experiment);
    app(CurrentOrganization::class)->forget();

    expect($results[0]['conversion_rate'])->toBe(0.0)->and($results[0]['lift'])->toBe(0.0);
});

it('creates an experiment through the controller', function () {
    [, $owner] = analyticsOrganization();

    $this->actingAs($owner)->post(route('analytics.experiments.store'), [
        'name' => 'Offer test',
        'type' => 'offer',
        'variants' => [['name' => 'Control'], ['name' => 'Discount']],
    ])->assertRedirect();

    expect(Experiment::withoutGlobalScope('tenant')->firstWhere('name', 'Offer test'))->not->toBeNull();
});
