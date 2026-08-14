<?php

namespace Database\Seeders;

use App\Authorization\Role;
use App\Billing\Entitlements;
use App\Models\AdCampaign;
use App\Models\AdMetric;
use App\Models\AiPrompt;
use App\Models\BookingPage;
use App\Models\BrandProfile;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Deal;
use App\Models\Keyword;
use App\Models\KeywordRanking;
use App\Models\KpiTarget;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\Plan;
use App\Models\Review;
use App\Models\ScoringRule;
use App\Models\ServiceLine;
use App\Models\User;
use App\Models\Vertical;
use App\Services\Delivery\ProjectService;
use App\Services\OrganizationService;
use App\Services\Sales\BookingService;
use App\Services\Strategy\PerformanceService;
use App\Services\SubscriptionService;
use App\Services\Web\LocationService;
use App\Services\Web\SiteBuilderService;
use App\Support\CurrentOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A coherent walkthrough dataset for a demo/staging instance: one MSP on the
 * Professional plan with data across every module, so each dashboard shows real
 * numbers that add up rather than zeros.
 *
 * Deliberately NOT part of DatabaseSeeder — run it explicitly:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * It refuses to run in production unless DEMO_SEED_ALLOWED=true, because it
 * creates a known-password login. Re-running is safe: it skips if the demo
 * organization already exists.
 */
class DemoSeeder extends Seeder
{
    private const EMAIL = 'demo@piotrack.test';

    private const PASSWORD = 'piotrack-demo-2026';

    private const ORG = 'Northwind IT Services';

    public function run(): void
    {
        if (app()->isProduction() && ! filter_var(config('app.demo_seed_allowed', false), FILTER_VALIDATE_BOOL)) {
            $this->command->warn('DemoSeeder skipped in production. Set DEMO_SEED_ALLOWED=true to allow it.');

            return;
        }

        if (User::where('email', self::EMAIL)->exists()) {
            $this->command->info('Demo data already present — nothing to do.');

            return;
        }

        $this->call(PlanSeeder::class);

        [$organization, $owner] = $this->organization();
        app(CurrentOrganization::class)->set($organization);

        $companies = $this->crm();
        $this->sales($companies);
        $this->marketingAndSeo();
        $this->advertising();
        $this->contentAndReputation();
        $this->websiteAndLocations();
        $this->strategyAndDelivery($owner);

        app(CurrentOrganization::class)->forget();

        $this->command->newLine();
        $this->command->info('Demo data ready.');
        $this->command->table(
            ['Sign in at', 'Email', 'Password'],
            [['/login', self::EMAIL, self::PASSWORD]],
        );
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function organization(): array
    {
        $owner = User::create([
            'name' => 'Dana Whitfield',
            'email' => self::EMAIL,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
        ]);

        $organization = app(OrganizationService::class)->create($owner, self::ORG);

        // Professional unlocks sales, analytics, AI and advertising for the demo.
        $plan = Plan::where('code', 'professional')->firstOrFail();
        app(SubscriptionService::class)->checkout($organization, $plan, 'monthly', 1, null);
        app(Entitlements::class)->forget($organization);

        // A colleague, so member lists and assignment are not a single row.
        $colleague = User::create([
            'name' => 'Marcus Reed',
            'email' => 'marcus@piotrack.test',
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
        ]);
        $organization->members()->attach($colleague->id, [
            'role' => Role::MarketingManager->value, 'status' => 'active', 'joined_at' => now(),
        ]);

        return [$organization, $owner];
    }

    /**
     * @return array<string, Company>
     */
    private function crm(): array
    {
        $companies = [];
        foreach ([
            ['Harbour Legal Group', 'legal', 'Toronto', 'Ontario', 120],
            ['Meridian Health Clinics', 'healthcare', 'Toronto', 'Ontario', 300],
            ['Cascade Manufacturing', 'manufacturing', 'Calgary', 'Alberta', 85],
            ['Bright Path Accounting', 'accounting', 'Ottawa', 'Ontario', 40],
        ] as [$name, $industry, $city, $region, $size]) {
            $companies[$name] = Company::create([
                'name' => $name, 'industry' => $industry, 'city' => $city,
                'region' => $region, 'size' => (string) $size, 'country' => 'Canada',
            ]);
        }

        $people = [
            ['Alice', 'Nguyen', 'IT Director', 'Harbour Legal Group', 'organic', 'sql', 78],
            ['Ben', 'Okafor', 'Practice Manager', 'Harbour Legal Group', 'organic', 'mql', 54],
            ['Chloe', 'Martin', 'CIO', 'Meridian Health Clinics', 'paid', 'sql', 82],
            ['Daniel', 'Ross', 'Operations Lead', 'Meridian Health Clinics', 'referral', 'lead', 31],
            ['Elena', 'Petrov', 'Plant Manager', 'Cascade Manufacturing', 'paid', 'mql', 47],
            ['Farid', 'Haddad', 'Controller', 'Bright Path Accounting', 'organic', 'lead', 22],
            ['Grace', 'Lin', 'Managing Partner', 'Bright Path Accounting', 'referral', 'customer', 91],
        ];

        foreach ($people as [$first, $last, $title, $company, $source, $stage, $score]) {
            Contact::create([
                'first_name' => $first, 'last_name' => $last, 'title' => $title,
                'email' => strtolower($first).'@'.str_replace(' ', '', strtolower($company)).'.test',
                'phone' => '+1416555'.random_int(1000, 9999),
                'company_id' => $companies[$company]->id,
                'lead_source' => $source, 'lifecycle_stage' => $stage,
                'lead_score' => $score, 'email_opt_in' => true,
            ]);
        }

        return $companies;
    }

    /**
     * @param  array<string, Company>  $companies
     */
    private function sales(array $companies): void
    {
        $pipeline = Pipeline::where('is_default', true)->firstOrFail();
        $stages = $pipeline->stages()->orderBy('sort_order')->get();
        $won = $stages->firstWhere('is_won', true);
        $proposal = $stages->firstWhere('name', 'Proposal') ?? $stages->get(2);
        $qualified = $stages->get(1) ?? $stages->first();

        $deals = [
            ['Harbour Legal — Managed IT', 'Harbour Legal Group', 1_440_000, 120_000, $won, 'organic', 'spring-managed-it'],
            ['Meridian Health — Security + M365', 'Meridian Health Clinics', 2_160_000, 180_000, $proposal, 'paid', 'healthcare-security'],
            ['Cascade — Co-managed IT', 'Cascade Manufacturing', 960_000, 80_000, $qualified, 'paid', 'spring-managed-it'],
            ['Bright Path — Backup & DR', 'Bright Path Accounting', 480_000, 40_000, $won, 'referral', null],
        ];

        foreach ($deals as [$name, $company, $value, $mrr, $stage, $source, $campaign]) {
            $isWon = (bool) $stage->is_won;
            Deal::create([
                'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id,
                'company_id' => $companies[$company]->id,
                'name' => $name, 'value' => $value, 'mrr' => $mrr, 'arr' => $mrr * 12,
                'ltv' => $mrr * 36, 'contract_term_months' => 36, 'currency' => 'CAD',
                'status' => $isWon ? 'won' : 'open',
                'lead_source' => $source, 'campaign' => $campaign,
                'closed_at' => $isWon ? now()->subDays(random_int(5, 40)) : null,
                'expected_close_date' => $isWon ? null : now()->addDays(random_int(10, 60)),
            ]);
        }

        // Lead scoring rules so the sales module shows a working engine.
        ScoringRule::create(['name' => 'Decision maker title', 'category' => 'demographic', 'attribute' => 'title', 'operator' => 'contains', 'value' => 'director', 'points' => 25, 'is_active' => true]);
        ScoringRule::create(['name' => 'Reached MQL', 'category' => 'behavioral', 'attribute' => 'lifecycle_stage', 'operator' => 'equals', 'value' => 'mql', 'points' => 20, 'is_active' => true]);
        ScoringRule::create(['name' => 'Opted in to email', 'category' => 'behavioral', 'attribute' => 'email_opt_in', 'operator' => 'is_true', 'points' => 15, 'is_active' => true]);

        // A booking page plus two booked meetings.
        $page = BookingPage::create([
            'name' => 'Free IT Assessment', 'slug' => 'demo-assessment',
            'meeting_type' => 'consultation', 'duration_minutes' => 30,
            'assignment' => 'round_robin', 'is_active' => true,
        ]);

        foreach ([['Alice Nguyen', 'alice@harbourlegalgroup.test'], ['Chloe Martin', 'chloe@meridianhealthclinics.test']] as [$name, $email]) {
            app(BookingService::class)->book($page, [
                'name' => $name, 'email' => $email,
                'scheduled_at' => now()->addDays(random_int(1, 10)),
                'notes' => 'Booked from the demo dataset.',
            ]);
        }
    }

    private function marketingAndSeo(): void
    {
        $keywords = [
            ['managed it services toronto', 2, 880], ['msp toronto', 4, 720],
            ['cybersecurity services toronto', 7, 540], ['microsoft 365 migration', 11, 390],
            ['it support for law firms', 3, 210], ['hipaa compliant it support', 16, 170],
            ['disaster recovery services', 24, 260], ['co-managed it services', 9, 140],
        ];

        foreach ($keywords as [$phrase, $position, $volume]) {
            $keyword = Keyword::create([
                'phrase' => $phrase, 'intent' => 'commercial', 'type' => 'primary',
                'search_volume' => $volume, 'difficulty' => random_int(20, 60),
                'is_tracked' => true, 'current_position' => $position,
            ]);

            KeywordRanking::create([
                'keyword_id' => $keyword->id, 'engine' => 'google', 'location' => 'Toronto',
                'position' => $position, 'is_competitor' => false, 'checked_at' => now(),
            ]);

            // A competitor ranking so share-of-voice has something to compare.
            KeywordRanking::create([
                'keyword_id' => $keyword->id, 'engine' => 'google', 'location' => 'Toronto',
                'position' => min(100, $position + random_int(1, 6)),
                'is_competitor' => true, 'competitor_domain' => 'rival-msp.test', 'checked_at' => now(),
            ]);
        }

        // AI-visibility prompt library (Stage 12).
        foreach ([
            ['best managed IT provider in Toronto', 'managed it', 'Toronto'],
            ['who should a law firm use for IT support', 'it support', null],
            ['HIPAA compliant MSP for clinics', 'compliance', null],
        ] as [$text, $service, $city]) {
            AiPrompt::create(['text' => $text, 'category' => 'discovery', 'service' => $service, 'city' => $city, 'is_active' => true]);
        }
    }

    private function advertising(): void
    {
        foreach ([
            ['Brand — Managed IT', 'google_search', 240_000, 24_000, 1_800, 36, 1_200_000],
            ['Healthcare Security', 'linkedin', 180_000, 12_000, 640, 14, 720_000],
        ] as [$name, $platform, $spend, $impressions, $clicks, $conversions, $revenue]) {
            $campaign = AdCampaign::create([
                'platform' => $platform, 'name' => $name, 'objective' => 'leads',
                'status' => 'active', 'daily_budget' => (int) round($spend / 30),
                'start_date' => now()->subDays(30)->toDateString(),
            ]);

            // Thirty days of daily metrics so trends and KPIs are non-trivial.
            for ($day = 29; $day >= 0; $day--) {
                AdMetric::create([
                    'ad_campaign_id' => $campaign->id,
                    'date' => now()->subDays($day)->startOfDay(),
                    'impressions' => (int) round($impressions / 30 * random_int(80, 120) / 100),
                    'clicks' => (int) round($clicks / 30 * random_int(80, 120) / 100),
                    'spend' => (int) round($spend / 30),
                    'conversions' => $day % 2 === 0 ? (int) ceil($conversions / 15) : 0,
                    'revenue' => (int) round($revenue / 30),
                ]);
            }
        }
    }

    private function contentAndReputation(): void
    {
        foreach ([
            ['Why Toronto law firms are moving to co-managed IT', 'published', 92],
            ['HIPAA compliance checklist for clinics', 'published', 88],
            ['What a real disaster recovery plan looks like', 'draft', 61],
        ] as [$title, $status, $score]) {
            ContentPiece::create([
                'title' => $title, 'slug' => str($title)->slug()->value(),
                'content_type' => 'article', 'funnel_stage' => 'awareness', 'status' => $status,
                'body' => 'Demo content body for the walkthrough dataset.',
                'optimization_score' => $score,
                'published_at' => $status === 'published' ? now()->subDays(random_int(3, 40)) : null,
            ]);
        }

        foreach ([
            ['Google', 5, 'Fast, proactive and genuinely helpful. Response times are excellent.'],
            ['Google', 5, 'Migrated our whole practice to M365 without a day of downtime.'],
            ['Clutch', 4, 'Strong security work. Reporting could be a little more frequent.'],
            ['Google', 3, 'Good team, though onboarding took longer than we expected.'],
        ] as [$source, $rating, $body]) {
            Review::create([
                'source' => $source, 'rating' => $rating, 'body' => $body,
                'author_name' => 'Verified client',
                'sentiment' => $this->sentimentFor($rating),
                'reviewed_at' => now()->subDays(random_int(5, 90)),
            ]);
        }
    }

    /**
     * Mirrors the Stage 9 ReputationService rule: 4+ positive, 3 neutral, below
     * that negative.
     */
    private function sentimentFor(int $rating): string
    {
        return match (true) {
            $rating >= 4 => 'positive',
            $rating === 3 => 'neutral',
            default => 'negative',
        };
    }

    private function websiteAndLocations(): void
    {
        $locations = app(LocationService::class);
        $toronto = $locations->create([
            'name' => 'Toronto HQ', 'street' => '120 Adelaide St W', 'city' => 'Toronto',
            'region' => 'Ontario', 'postal_code' => 'M5H 1T1', 'country' => 'Canada',
            'phone' => '+14165550100', 'territory' => 'Ontario',
        ]);
        $locations->create([
            'name' => 'Calgary Branch', 'street' => '888 3 St SW', 'city' => 'Calgary',
            'region' => 'Alberta', 'postal_code' => 'T2P 5C5', 'country' => 'Canada',
            'phone' => '+14035550100', 'territory' => 'Alberta',
        ]);

        $builder = app(SiteBuilderService::class);
        $managedIt = ServiceLine::where('key', 'managed_it')->first();
        $healthcare = Vertical::where('key', 'healthcare')->first();

        $pages = [
            ['Managed IT Services', 'service', 'Managed IT that just works', $managedIt?->id, null, null],
            ['IT Support for Healthcare', 'vertical', 'HIPAA-ready IT for clinics', null, $healthcare?->id, null],
            ['IT Support in Toronto', 'location', 'Local IT support, on site when you need it', null, null, $toronto->id],
        ];

        foreach ($pages as [$title, $type, $headline, $service, $vertical, $location]) {
            $page = $builder->createPage([
                'title' => $title, 'type' => $type, 'headline' => $headline,
                'subheadline' => 'Proactive support, security and cloud for growing organizations.',
                'meta_title' => $title.' | '.self::ORG,
                'meta_description' => 'Northwind IT Services delivers '.strtolower($title).' with proactive monitoring, security and a responsive help desk.',
                'service_line_id' => $service, 'vertical_id' => $vertical, 'seo_location_id' => $location,
            ]);

            $builder->addSection($page, ['type' => 'hero', 'heading' => $headline]);
            $builder->addSection($page, ['type' => 'services', 'heading' => 'What we cover', 'settings' => ['items' => ['Help desk', 'Security', 'Cloud & M365', 'Backup & DR']]]);
            $builder->addSection($page, ['type' => 'testimonials', 'heading' => 'What clients say', 'settings' => ['items' => ['"Fast and proactive." — Harbour Legal', '"Zero downtime migration." — Meridian Health']]]);
            $builder->addSection($page, ['type' => 'cta', 'heading' => 'Book a free IT assessment', 'body' => 'Thirty minutes, no obligation.']);

            $builder->publish($page);
        }
    }

    private function strategyAndDelivery(User $owner): void
    {
        BrandProfile::create([
            'positioning_statement' => 'The MSP that professional-services firms trust with compliance-critical IT.',
            'usp' => 'Compliance-first managed IT with a 15-minute response SLA.',
            'value_proposition' => 'Predictable IT costs, audit-ready security, and a help desk that answers.',
            'differentiators' => ['Compliance specialism', '15-minute response SLA', 'Named vCIO'],
            'tone_of_voice' => 'Direct, plain-spoken, no jargon',
            'elevator_pitch' => 'We run IT for law firms, clinics and accountants who cannot afford downtime or a failed audit.',
            'tagline' => 'IT that holds up under audit.',
        ]);

        foreach ([
            ['leads', 40], ['sqls', 12], ['meetings', 8], ['mrr', 500_000],
        ] as [$metric, $target]) {
            KpiTarget::create([
                'metric' => $metric, 'target_value' => $target,
                'period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth(),
            ]);
        }

        app(PerformanceService::class)->create([
            'name' => 'Q3 lead guarantee', 'model' => 'guarantee',
            'lead_target' => 30, 'sql_target' => 10, 'meeting_target' => 6,
            'quality_criteria' => ['min_lead_score' => 40, 'has_company' => true],
            'sla_days' => 30,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->addMonth()->toDateString(),
        ]);

        $projects = app(ProjectService::class);
        $project = $projects->create([
            'name' => 'Q3 Growth Engagement', 'status' => 'active',
            'description' => 'SEO, paid media and conversion work for the Toronto market.',
            'starts_on' => now()->startOfMonth()->toDateString(),
            'ends_on' => now()->addMonths(3)->toDateString(),
        ]);
        $projects->assign($project, $owner, 'strategist');
        $sprint = $projects->startSprint($project, ['name' => 'Sprint 1', 'goal' => 'Launch the service pages and brand campaign.']);

        foreach ([
            ['Publish managed IT service page', 'done'],
            ['Launch brand search campaign', 'done'],
            ['Rewrite homepage hero', 'in_progress'],
            ['Set up call tracking numbers', 'todo'],
        ] as [$title, $status]) {
            $projects->addTask($project, ['title' => $title, 'sprint_id' => $sprint->id, 'status' => $status]);
        }

        $deliverable = $projects->addDeliverable($project, [
            'title' => 'Q3 strategy deck', 'type' => 'document',
            'notes' => 'Positioning, channel plan and KPI targets.',
        ]);
        $projects->submitForApproval($deliverable);
    }
}
