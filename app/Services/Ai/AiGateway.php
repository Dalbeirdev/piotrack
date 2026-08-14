<?php

namespace App\Services\Ai;

use App\Ai\AiCompletion;
use App\Ai\AiProviderManager;
use App\Ai\Exceptions\AiCreditsExhaustedException;
use App\Ai\Exceptions\AiProviderException;
use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Models\AiRequest;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;

/**
 * The single entry point for every language-model call (ADR-0008). No feature
 * talks to a provider directly, which is what makes the governance
 * non-bypassable rather than a convention:
 *
 *  - resolves + renders the active versioned prompt (AIPF-001)
 *  - refuses the call when the tenant is out of `ai_credits` BEFORE any spend
 *    is incurred (AIPF-004)
 *  - retries only transient provider failures, bounded (AIPF-005)
 *  - records tokens + estimated cost per tenant/user/feature (AIPF-003)
 *  - audits success and failure; a failed call is never billed as usage and
 *    never silently substitutes fabricated content
 */
class AiGateway
{
    public function __construct(
        private AiProviderManager $providers,
        private PromptRegistry $prompts,
        private UsageMeter $usage,
        private CurrentOrganization $current,
        private AuditLogger $audit,
    ) {}

    /**
     * Run a prompt template through the model.
     *
     * @param  array<string, string|int|null>  $variables
     *
     * @throws AiCreditsExhaustedException when the plan's AI credits are exhausted
     * @throws AiProviderException when the provider fails after its retries
     */
    public function run(string $feature, string $promptKey, array $variables = []): AiCompletion
    {
        $organization = $this->current->get();

        // Hard cap before spend: an out-of-credit tenant never reaches a provider.
        if ($organization !== null && ! $this->usage->withinLimit($organization, Limit::AiCredits)) {
            $this->audit->log('ai.request.refused', context: ['feature' => $feature, 'reason' => 'ai_credits_exhausted']);

            throw new AiCreditsExhaustedException;
        }

        $rendered = $this->prompts->render($promptKey, $variables);
        $provider = $this->providers->driver();
        $maxAttempts = max(1, (int) config('ai.max_attempts', 3));

        $startedAt = microtime(true);
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $completion = $provider->complete($rendered['prompt'], $rendered['system']);
            } catch (AiProviderException $e) {
                // Only transient failures are worth retrying; permanent ones fail fast.
                if ($e->transient && $attempt < $maxAttempts) {
                    continue;
                }

                $this->record($feature, $provider->name(), $provider->model(), $rendered['template']->id, 0, 0, 0, $startedAt, $attempt, 'failed', $e->getMessage());
                $this->audit->log('ai.request.failed', context: ['feature' => $feature, 'attempts' => $attempt, 'error' => $e->getMessage()]);

                throw $e;
            }

            $cost = $this->estimateCost($completion);

            $request = $this->record(
                $feature, $provider->name(), $completion->model, $rendered['template']->id,
                $completion->promptTokens, $completion->completionTokens, $cost, $startedAt, $attempt, 'succeeded', null,
            );

            // Usage is consumed only for calls that actually produced output.
            if ($organization !== null) {
                $this->usage->increment($organization, Limit::AiCredits);
            }

            $this->audit->log('ai.request.completed', context: [
                'feature' => $feature,
                'model' => $completion->model,
                'tokens' => $completion->totalTokens(),
                'cost' => $cost,
            ], resourceType: 'ai_request', resourceId: (string) $request->id);

            return $completion;
        }
    }

    /**
     * The most recent request row, so callers can link a message to its call.
     */
    public function lastRequestId(): ?int
    {
        return AiRequest::latest('id')->value('id');
    }

    /**
     * Estimated cost in minor units from the configured per-million-token price.
     */
    public function estimateCost(AiCompletion $completion): int
    {
        /** @var array<string, array{prompt: int, completion: int}> $pricing */
        $pricing = config('ai.pricing', []);
        $rates = $pricing[$completion->model] ?? $pricing['default'] ?? ['prompt' => 0, 'completion' => 0];

        $cost = ($completion->promptTokens * $rates['prompt'] + $completion->completionTokens * $rates['completion']) / 1_000_000;

        return (int) round($cost);
    }

    /**
     * Cost + volume rollup for the tenant, by feature and by user (AIPF-003).
     *
     * @return array<string, mixed>
     */
    public function usageSummary(): array
    {
        $byFeature = AiRequest::query()
            ->selectRaw('feature, COUNT(*) AS requests, COALESCE(SUM(prompt_tokens + completion_tokens),0) AS tokens, COALESCE(SUM(estimated_cost),0) AS cost')
            ->groupBy('feature')->get()
            ->map(fn ($r) => [
                'feature' => (string) $r->feature,
                'requests' => (int) ($r->requests ?? 0),
                'tokens' => (int) ($r->tokens ?? 0),
                'cost' => (int) ($r->cost ?? 0),
            ])->all();

        $byUser = AiRequest::query()
            ->selectRaw('user_id, COUNT(*) AS requests, COALESCE(SUM(estimated_cost),0) AS cost')
            ->groupBy('user_id')->get()
            ->map(fn ($r) => [
                'user_id' => $r->user_id !== null ? (int) $r->user_id : null,
                'requests' => (int) ($r->requests ?? 0),
                'cost' => (int) ($r->cost ?? 0),
            ])->all();

        return [
            'total_requests' => AiRequest::count(),
            'failed_requests' => AiRequest::where('status', 'failed')->count(),
            'total_tokens' => (int) AiRequest::sum('prompt_tokens') + (int) AiRequest::sum('completion_tokens'),
            'total_cost' => (int) AiRequest::sum('estimated_cost'),
            'by_feature' => $byFeature,
            'by_user' => $byUser,
        ];
    }

    private function record(
        string $feature,
        string $provider,
        string $model,
        ?int $templateId,
        int $promptTokens,
        int $completionTokens,
        int $cost,
        float $startedAt,
        int $attempts,
        string $status,
        ?string $error,
    ): AiRequest {
        return AiRequest::create([
            'user_id' => request()->user()?->getAuthIdentifier(),
            'ai_prompt_template_id' => $templateId,
            'feature' => $feature,
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'estimated_cost' => $cost,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'attempts' => $attempts,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
