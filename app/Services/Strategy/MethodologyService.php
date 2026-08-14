<?php

namespace App\Services\Strategy;

use App\Models\AdMetric;
use App\Models\BrandProfile;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Form;
use App\Models\Keyword;
use App\Models\ScoringRule;
use App\Models\StrategyItem;
use App\Models\Workflow;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\AttributionService;

/**
 * The proprietary five-P methodology (METH): Position, Presence, Pipeline,
 * Pursuit, Profit. Each stage is scored 0–100 from REAL signals in the modules
 * that implement it, and every score carries the evidence behind it, so the
 * framework reports what the tenant has actually done rather than a self-
 * assessment. A stage with no data scores 0 and says why.
 */
class MethodologyService
{
    public function __construct(
        private AnalyticsService $analytics,
        private AttributionService $attribution,
    ) {}

    /**
     * @return list<array{stage: string, label: string, score: int, evidence: list<array{signal: string, value: int|string, met: bool}>}>
     */
    public function assess(): array
    {
        return [
            $this->stage('position', 'Position', $this->position()),
            $this->stage('presence', 'Presence', $this->presence()),
            $this->stage('pipeline', 'Pipeline', $this->pipeline()),
            $this->stage('pursuit', 'Pursuit', $this->pursuit()),
            $this->stage('profit', 'Profit', $this->profit()),
        ];
    }

    /**
     * The overall methodology score: the mean of the five stages.
     */
    public function overall(): int
    {
        $stages = $this->assess();

        return (int) round(collect($stages)->avg('score'));
    }

    /**
     * Define ICP, positioning, offer and target market.
     *
     * @return list<array{signal: string, value: int|string, met: bool}>
     */
    private function position(): array
    {
        $brand = BrandProfile::first();
        $research = StrategyItem::where('type', 'research')->count();

        return [
            $this->signal('Positioning statement defined', $brand?->positioning_statement !== null ? 'yes' : 'no', $brand?->positioning_statement !== null),
            $this->signal('Value proposition defined', $brand?->value_proposition !== null ? 'yes' : 'no', $brand?->value_proposition !== null),
            $this->signal('ICP / buyer research recorded', $research, $research > 0),
            $this->signal('Target market segmented (companies)', Contact::whereNotNull('company_id')->count(), Contact::whereNotNull('company_id')->exists()),
        ];
    }

    /**
     * SEO, AI search, maps, paid media and social.
     *
     * @return list<array{signal: string, value: int|string, met: bool}>
     */
    private function presence(): array
    {
        $keywords = Keyword::where('is_tracked', true)->count();
        $spend = (int) AdMetric::sum('spend');
        $content = ContentPiece::where('status', 'published')->count();
        $seo = $this->analytics->seo();

        return [
            $this->signal('Keywords tracked', $keywords, $keywords > 0),
            $this->signal('Ranking on page one', $seo['page_one'], $seo['page_one'] > 0),
            $this->signal('Paid media running', $spend > 0 ? 'yes' : 'no', $spend > 0),
            $this->signal('Content published', $content, $content > 0),
        ];
    }

    /**
     * Website, landing pages, forms and CRM.
     *
     * @return list<array{signal: string, value: int|string, met: bool}>
     */
    private function pipeline(): array
    {
        $forms = Form::count();
        $contacts = Contact::count();
        $funnel = $this->analytics->funnel();

        return [
            $this->signal('Capture forms live', $forms, $forms > 0),
            $this->signal('Contacts in CRM', $contacts, $contacts > 0),
            $this->signal('Leads reaching MQL', $funnel['mqls'], $funnel['mqls'] > 0),
            $this->signal('Opportunities created', $funnel['opportunities'] + $funnel['closed_won'], ($funnel['opportunities'] + $funnel['closed_won']) > 0),
        ];
    }

    /**
     * Automation, retargeting, scoring and sales follow-up.
     *
     * @return list<array{signal: string, value: int|string, met: bool}>
     */
    private function pursuit(): array
    {
        $workflows = Workflow::where('status', 'active')->count();
        $rules = ScoringRule::where('is_active', true)->count();
        $funnel = $this->analytics->funnel();

        return [
            $this->signal('Automation workflows active', $workflows, $workflows > 0),
            $this->signal('Lead scoring rules active', $rules, $rules > 0),
            $this->signal('Leads qualified to SQL', $funnel['sqls'], $funnel['sqls'] > 0),
            $this->signal('Meetings booked', $funnel['meetings'], $funnel['meetings'] > 0),
        ];
    }

    /**
     * Revenue attribution, MRR and ROI.
     *
     * @return list<array{signal: string, value: int|string, met: bool}>
     */
    private function profit(): array
    {
        $revenue = $this->analytics->revenue();
        $channels = $this->attribution->channelRevenue();
        $roi = $this->attribution->marketingRoi();

        return [
            $this->signal('Closed-won revenue', $revenue['contract_value'], $revenue['contract_value'] > 0),
            $this->signal('MRR recorded', $revenue['mrr'], $revenue['mrr'] > 0),
            $this->signal('Revenue attributed to channels', count($channels), count($channels) > 0),
            $this->signal('Marketing ROI measurable', $roi > 0 ? 'yes' : 'no', $roi > 0),
        ];
    }

    /**
     * @param  list<array{signal: string, value: int|string, met: bool}>  $evidence
     * @return array{stage: string, label: string, score: int, evidence: list<array{signal: string, value: int|string, met: bool}>}
     */
    private function stage(string $stage, string $label, array $evidence): array
    {
        $met = count(array_filter($evidence, fn (array $e) => $e['met']));
        $total = count($evidence);

        return [
            'stage' => $stage,
            'label' => $label,
            'score' => $total > 0 ? (int) round($met / $total * 100) : 0,
            'evidence' => $evidence,
        ];
    }

    /**
     * @return array{signal: string, value: int|string, met: bool}
     */
    private function signal(string $signal, int|string $value, bool $met): array
    {
        return ['signal' => $signal, 'value' => $value, 'met' => $met];
    }
}
