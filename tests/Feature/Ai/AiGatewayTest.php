<?php

use App\Ai\AiCompletion;
use App\Ai\AiProviderManager;
use App\Ai\Contracts\AiProvider;
use App\Ai\Exceptions\AiCreditsExhaustedException;
use App\Ai\Exceptions\AiProviderException;
use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Models\AiPromptTemplate;
use App\Models\AiRequest;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\Ai\AiGateway;
use App\Support\CurrentOrganization;

/**
 * An organization on a plan that includes the `ai` feature + AI credits.
 *
 * @return array{0: Organization, 1: User}
 */
function aiOrganization(string $name = 'Test Org'): array
{
    [$org, $owner] = makeOrganization($name);
    subscribeOrganization($org, 'professional'); // Professional includes `ai` + 5,000 credits

    return [$org, $owner];
}

/** Swap in a provider stub for failure/behaviour testing. */
function fakeAiProvider(callable $complete, string $name = 'stub'): void
{
    app()->bind(AiProvider::class, fn () => new class($complete, $name) implements AiProvider
    {
        public function __construct(private $complete, private string $name) {}

        public function complete(string $prompt, ?string $system = null): AiCompletion
        {
            return ($this->complete)($prompt, $system);
        }

        public function name(): string
        {
            return $this->name;
        }

        public function model(): string
        {
            return 'stub-1';
        }
    });

    // The manager resolves the driver, so point it at the bound stub too.
    app()->bind(AiProviderManager::class, fn () => new class extends AiProviderManager
    {
        public function driver(): AiProvider
        {
            return app(AiProvider::class);
        }
    });
}

it('records tokens, cost and the prompt version for a successful call', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    $completion = app(AiGateway::class)->run('sales.qualify', 'sales.qualify', ['name' => 'Ann']);
    app(CurrentOrganization::class)->forget();

    $request = AiRequest::withoutGlobalScope('tenant')->latest('id')->first();

    expect($completion->text)->not->toBeEmpty()
        ->and($request->status)->toBe('succeeded')
        ->and($request->feature)->toBe('sales.qualify')
        ->and($request->prompt_tokens)->toBeGreaterThan(0)
        ->and($request->completion_tokens)->toBeGreaterThan(0)
        // Every request is traceable to the exact prompt version that produced it.
        ->and($request->ai_prompt_template_id)->not->toBeNull();
});

it('substitutes prompt variables into the rendered prompt', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    $seen = null;
    fakeAiProvider(function (string $prompt) use (&$seen) {
        $seen = $prompt;

        return new AiCompletion('ok', 5, 5, 'stub-1');
    });

    app(AiGateway::class)->run('sales.qualify', 'sales.qualify', ['name' => 'Zora Vex', 'company' => 'Acme']);
    app(CurrentOrganization::class)->forget();

    expect($seen)->toContain('Zora Vex')->toContain('Acme')->not->toContain('{{name}}');
});

it('estimates cost from the configured per-million-token price', function () {
    config()->set('ai.pricing.stub-1', ['prompt' => 1000, 'completion' => 2000]);

    $cost = app(AiGateway::class)->estimateCost(new AiCompletion('x', 1_000_000, 1_000_000, 'stub-1'));

    expect($cost)->toBe(3000); // 1000 + 2000 minor units
});

it('refuses the call and never reaches the provider when credits are exhausted', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    $called = false;
    fakeAiProvider(function () use (&$called) {
        $called = true;

        return new AiCompletion('should not happen', 1, 1, 'stub-1');
    });

    // Consume the whole Professional allowance.
    app(UsageMeter::class)->increment($org, Limit::AiCredits, 5000);

    expect(fn () => app(AiGateway::class)->run('sales.qualify', 'sales.qualify'))
        ->toThrow(AiCreditsExhaustedException::class);

    app(CurrentOrganization::class)->forget();

    expect($called)->toBeFalse() // no spend incurred
        ->and(AuditLog::where('action', 'ai.request.refused')->exists())->toBeTrue();
});

it('consumes one credit per successful call', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    app(AiGateway::class)->run('sales.qualify', 'sales.qualify');
    app(AiGateway::class)->run('sales.qualify', 'sales.qualify');
    app(CurrentOrganization::class)->forget();

    expect(app(UsageMeter::class)->usage($org, Limit::AiCredits))->toBe(2);
});

it('retries a transient provider failure and then succeeds', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    $attempts = 0;
    fakeAiProvider(function () use (&$attempts) {
        $attempts++;
        if ($attempts < 3) {
            throw new AiProviderException('rate limited', transient: true);
        }

        return new AiCompletion('recovered', 10, 10, 'stub-1');
    });

    $completion = app(AiGateway::class)->run('sales.qualify', 'sales.qualify');
    app(CurrentOrganization::class)->forget();

    expect($attempts)->toBe(3)
        ->and($completion->text)->toBe('recovered')
        ->and(AiRequest::withoutGlobalScope('tenant')->latest('id')->first()->attempts)->toBe(3);
});

it('fails fast on a permanent provider failure without retrying', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    $attempts = 0;
    fakeAiProvider(function () use (&$attempts) {
        $attempts++;
        throw new AiProviderException('bad request', transient: false);
    });

    expect(fn () => app(AiGateway::class)->run('sales.qualify', 'sales.qualify'))
        ->toThrow(AiProviderException::class);
    app(CurrentOrganization::class)->forget();

    expect($attempts)->toBe(1);
});

it('does not bill usage for a failed call but does record it', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    fakeAiProvider(fn () => throw new AiProviderException('provider down', transient: false));

    try {
        app(AiGateway::class)->run('sales.qualify', 'sales.qualify');
    } catch (AiProviderException) {
        // expected
    }
    app(CurrentOrganization::class)->forget();

    $request = AiRequest::withoutGlobalScope('tenant')->latest('id')->first();

    expect(app(UsageMeter::class)->usage($org, Limit::AiCredits))->toBe(0) // not billed
        ->and($request->status)->toBe('failed')
        ->and($request->error)->toContain('provider down')
        ->and(AuditLog::where('action', 'ai.request.failed')->exists())->toBeTrue();
});

it('summarizes cost by feature and by user', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    config()->set('ai.pricing.fixture-1', ['prompt' => 1_000_000, 'completion' => 1_000_000]);

    app(AiGateway::class)->run('sales.qualify', 'sales.qualify');
    app(AiGateway::class)->run('sales.draft_email', 'sales.draft_email', ['name' => 'A']);

    $summary = app(AiGateway::class)->usageSummary();
    app(CurrentOrganization::class)->forget();

    expect($summary['total_requests'])->toBe(2)
        ->and($summary['failed_requests'])->toBe(0)
        ->and($summary['total_cost'])->toBeGreaterThan(0)
        ->and(collect($summary['by_feature'])->pluck('feature')->all())
        ->toContain('sales.qualify', 'sales.draft_email');
});

it('seeds a built-in prompt template on first use', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    app(AiGateway::class)->run('sales.qualify', 'sales.qualify');
    app(CurrentOrganization::class)->forget();

    $template = AiPromptTemplate::withoutGlobalScope('tenant')->where('key', 'sales.qualify')->first();

    expect($template)->not->toBeNull()
        ->and($template->version)->toBe(1)
        ->and($template->is_active)->toBeTrue()
        ->and($template->organization_id)->toBe($org->id);
});
