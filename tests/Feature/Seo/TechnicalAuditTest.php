<?php

use App\Models\AuditLog;
use App\Models\SeoAudit;
use App\Services\Seo\TechnicalSeoAuditor;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Http;

/** A fully-optimized page — every technical check should pass. */
function seoGoodHtml(): string
{
    return '<!DOCTYPE html><html lang="en"><head>'
        .'<title>Managed IT Services in Dallas for Growing MSPs</title>'
        .'<meta name="description" content="Managed IT, cybersecurity and cloud services for Dallas small businesses with round-the-clock support.">'
        .'<meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<link rel="canonical" href="https://acme.test/services">'
        .'<meta property="og:title" content="Managed IT Services">'
        .'<script type="application/ld+json">{"@context":"https://schema.org"}</script>'
        .'</head><body><h1>Managed IT Services</h1><h2>What we do</h2>'
        .'<img src="/a.png" alt="Our team"><ul><li>Support</li></ul>'
        .'<p>'.str_repeat('word ', 350).'</p></body></html>';
}

it('scores a well-optimized page highly with no issues', function () {
    $result = app(TechnicalSeoAuditor::class)->analyze(seoGoodHtml(), 'https://acme.test/services');

    expect($result->score)->toBeGreaterThan(90)
        ->and($result->issuesCount)->toBe(0);
});

it('flags a poorly-optimized page', function () {
    $html = '<html><head></head><body><h1>A</h1><h1>B</h1><img src="x"></body></html>';

    $result = app(TechnicalSeoAuditor::class)->analyze($html, 'http://acme.test');

    expect($result->score)->toBeLessThan(60);

    $failing = collect($result->checks)->where('status', '!=', 'pass')->pluck('key');
    expect($failing)->toContain('title')      // missing
        ->toContain('meta_description')         // missing
        ->toContain('viewport')                 // missing → not mobile-friendly
        ->toContain('https')                    // http
        ->toContain('h1');                      // two H1s
});

it('crawls a URL and persists a scored audit', function () {
    [$org] = makeOrganization();
    Http::fake(['*' => Http::response(seoGoodHtml(), 200)]);

    app(CurrentOrganization::class)->set($org);
    // A public IP literal: no DNS, and it exercises the SSRF guard's allow path.
    $audit = app(TechnicalSeoAuditor::class)->crawl('https://93.184.216.34/services');
    app(CurrentOrganization::class)->forget();

    expect($audit->score)->toBeGreaterThan(90)
        ->and($audit->fetched_status)->toBe(200)
        ->and($audit->organization_id)->toBe($org->id);
    expect(SeoAudit::withoutGlobalScope('tenant')->count())->toBe(1);
});

it('records a failed audit when the page cannot be fetched', function () {
    [$org] = makeOrganization();
    Http::fake(['*' => Http::response('', 500)]);

    app(CurrentOrganization::class)->set($org);
    $audit = app(TechnicalSeoAuditor::class)->crawl('https://93.184.216.34/down');
    app(CurrentOrganization::class)->forget();

    expect($audit->score)->toBe(0)
        ->and($audit->issues_count)->toBe(1)
        ->and($audit->fetched_status)->toBe(500);
});

it('runs an audit from the controller and audits it', function () {
    [$org, $owner] = makeOrganization();
    Http::fake(['*' => Http::response(seoGoodHtml(), 200)]);

    $this->actingAs($owner)->post(route('seo.audits.store'), ['url' => 'https://93.184.216.34/services'])->assertRedirect();

    expect(SeoAudit::withoutGlobalScope('tenant')->count())->toBe(1);
    expect(AuditLog::withoutGlobalScope('tenant')->where('action', 'seo.audit.run')->exists())->toBeTrue();
});
