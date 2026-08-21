<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AnalyticsService;
use App\Services\OnboardingChecklist;
use App\Support\CurrentOrganization;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        CurrentOrganization $currentOrganization,
        OnboardingChecklist $checklist,
        AnalyticsService $analytics,
    ): Response {
        // Funnel and revenue are CRM-derived and need no analytics entitlement -
        // gating is applied on the Analytics module's own routes, not here. This
        // turns the landing page into a real command centre instead of the
        // starter-kit placeholder.
        $funnel = $analytics->funnel();
        $revenue = $analytics->revenue();

        return Inertia::render('dashboard', [
            'onboarding' => $checklist->for($currentOrganization->get()),
            'metrics' => [
                'leads' => $funnel['leads'],
                'sqls' => $funnel['sqls'],
                'meetings' => $funnel['meetings'],
                'opportunities' => $funnel['opportunities'],
                // Money is stored in minor units; the page renders it as dollars.
                'qualified_pipeline' => $funnel['qualified_pipeline'],
                'closed_won' => $funnel['closed_won'],
                'mrr' => $revenue['mrr'],
                'arr' => $revenue['arr'],
            ],
            'sources' => $analytics->sourceBreakdown(),
        ]);
    }
}
