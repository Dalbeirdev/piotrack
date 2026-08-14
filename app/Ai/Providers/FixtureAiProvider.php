<?php

namespace App\Ai\Providers;

use App\Ai\AiCompletion;
use App\Ai\Contracts\AiProvider;

/**
 * The default, fully-tested language-model driver: deterministic completions
 * derived from the rendered prompt (ADR-0008). It produces structurally useful
 * output — the shape each feature expects — with realistic token counts, so the
 * whole pipeline (prompts, retries, cost, limits, confirmation, every agent
 * feature) runs for real in dev and tests without credentials.
 *
 * It is explicitly NOT a language model: output is templated, not generated, and
 * the UI labels it as a fixture model so it is never mistaken for live inference.
 */
class FixtureAiProvider implements AiProvider
{
    public function complete(string $prompt, ?string $system = null): AiCompletion
    {
        $seed = crc32(mb_strtolower(trim($prompt)));
        $text = $this->respond($prompt, $seed);

        return new AiCompletion(
            text: $text,
            promptTokens: (int) ceil(mb_strlen($prompt.(string) $system) / 4),
            completionTokens: (int) ceil(mb_strlen($text) / 4),
            model: $this->model(),
        );
    }

    public function name(): string
    {
        return 'fixture';
    }

    public function model(): string
    {
        return 'fixture-1';
    }

    /**
     * Shape the response to the asking feature so downstream parsing is exercised.
     */
    private function respond(string $prompt, int $seed): string
    {
        $lower = mb_strtolower($prompt);

        return match (true) {
            str_contains($lower, 'score') => $this->scoreResponse($seed),
            str_contains($lower, 'qualify') || str_contains($lower, 'qualification') => $this->qualificationResponse($seed),
            str_contains($lower, 'summar') => 'Summary: the prospect discussed their current MSP contract, raised concerns about response times, and asked about onboarding effort. Next step agreed: send a scoped proposal.',
            str_contains($lower, 'objection') => 'Acknowledge the concern, reframe around total cost of downtime, and offer a reference from a similar-sized client.',
            str_contains($lower, 'next best action') => 'Book a 20-minute scoping call this week; the prospect has engaged with pricing twice.',
            str_contains($lower, 'email') || str_contains($lower, 'follow-up') || str_contains($lower, 'follow up') => "Subject: Following up on your IT support review\n\nHi there,\n\nThanks for taking the time to look at our managed services. Based on what you shared, I have put together a short outline of how we would cover your helpdesk and backup gaps.\n\nWould a 20-minute call this week work?\n\nBest regards",
            str_contains($lower, 'proposal') => "Proposal outline:\n1. Current-state summary\n2. Recommended managed services scope\n3. Service levels and response targets\n4. Onboarding plan and timeline\n5. Investment and terms",
            str_contains($lower, 'research') => 'Company appears to be a mid-market professional services firm; likely drivers are compliance and helpdesk responsiveness. Public signals suggest recent headcount growth.',
            default => 'Acknowledged. Here is a concise, structured response based on the information provided.',
        };
    }

    private function scoreResponse(int $seed): string
    {
        $score = ($seed % 61) + 40; // 40–100

        return "SCORE: {$score}\nREASON: Engagement level, fit with the ideal customer profile, and recent buying signals.";
    }

    private function qualificationResponse(int $seed): string
    {
        $qualified = ($seed % 3) !== 0;

        return $qualified
            ? "QUALIFIED: yes\nREASON: Budget authority indicated, timeline within a quarter, and a stated pain the service addresses."
            : "QUALIFIED: no\nREASON: No stated budget or timeline; treat as long-term nurture.";
    }
}
