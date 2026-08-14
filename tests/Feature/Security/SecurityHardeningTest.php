<?php

use App\Models\SeoAudit;
use App\Services\Seo\TechnicalSeoAuditor;
use App\Support\CurrentOrganization;
use App\Support\UrlGuard;
use Illuminate\Support\Facades\Http;

/**
 * SEC-001 — SSRF. The technical SEO auditor fetches a URL a tenant typed. Without
 * a guard it would fetch cloud metadata or internal services on their behalf,
 * using our server as the attacker.
 */
it('blocks cloud metadata, loopback and private addresses', function (string $url) {
    expect(app(UrlGuard::class)->isFetchable($url))->toBeFalse();
})->with([
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'link-local' => 'http://169.254.1.1/',
    'loopback ip' => 'http://127.0.0.1/admin',
    'loopback name' => 'http://localhost:6379',
    'private 10/8' => 'http://10.0.0.5/internal',
    'private 192.168' => 'http://192.168.1.1',
    'private 172.16' => 'http://172.16.0.10',
]);

it('blocks non-http schemes', function (string $url) {
    expect(app(UrlGuard::class)->isFetchable($url))->toBeFalse();
})->with([
    'file' => 'file:///etc/passwd',
    'gopher' => 'gopher://evil.test/_',
    'ftp' => 'ftp://internal.test/secrets',
    'not a url' => 'just-a-string',
]);

it('blocks unusual ports', function () {
    expect(app(UrlGuard::class)->isFetchable('http://93.184.216.34:22/'))->toBeFalse();
});

it('allows a public https url', function () {
    // A public IP literal keeps the assertion off DNS.
    expect(app(UrlGuard::class)->isFetchable('https://93.184.216.34/page'))->toBeTrue();
});

it('refuses to crawl an internal address and records the failure', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);

    // No HTTP call may be attempted at all.
    Http::fake();

    $audit = app(TechnicalSeoAuditor::class)->crawl('http://169.254.169.254/latest/meta-data/');
    app(CurrentOrganization::class)->forget();

    Http::assertNothingSent();
    expect($audit)->toBeInstanceOf(SeoAudit::class)
        ->and($audit->status)->not->toBe(200);
});

/**
 * SEC-002 — baseline response headers.
 */
it('sends security headers on every response', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'");
});

it('does not assert HSTS over plain http', function () {
    // Pinning a developer's browser to https on localhost would be a bug.
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
});

/**
 * SEC-006 — inbound webhook signature verification (built in Stage 3, asserted
 * here as part of the hardening pass).
 */
it('rejects a webhook with an invalid signature', function () {
    $this->postJson('/webhooks/stripe', ['type' => 'invoice.paid'])
        ->assertStatus(400)
        ->assertJson(['error' => 'invalid signature']);
});

it('404s an unknown webhook provider', function () {
    $this->postJson('/webhooks/not-a-provider', [])->assertNotFound();
});

/**
 * SEC-004 — no secrets in the repository.
 */
it('keeps credentials out of the committed config', function () {
    $configs = glob(base_path('config/*.php')) ?: [];

    foreach ($configs as $file) {
        $contents = (string) file_get_contents($file);

        // Real keys must arrive through env(), never as literals.
        expect($contents)->not->toMatch('/[\'"](sk_live_|sk_test_|whsec_|AKIA)[A-Za-z0-9]+[\'"]/');
    }
});
