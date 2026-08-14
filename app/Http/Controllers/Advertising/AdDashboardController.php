<?php

namespace App\Http\Controllers\Advertising;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\RetargetingAudience;
use App\Services\Advertising\AdMetricsService;
use Inertia\Inertia;
use Inertia\Response;

class AdDashboardController extends Controller
{
    public function __construct(private AdMetricsService $metrics) {}

    public function __invoke(): Response
    {
        return Inertia::render('advertising/dashboard', [
            'kpi' => $this->metrics->organizationKpi(now()->subDays(30))->toArray(),
            'stats' => [
                'campaigns' => AdCampaign::count(),
                'active' => AdCampaign::where('status', 'active')->count(),
                'audiences' => RetargetingAudience::count(),
            ],
            'campaigns' => AdCampaign::latest('id')->limit(8)->get()->map(fn (AdCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'platform' => $c->platform,
                'status' => $c->status,
                'kpi' => $this->metrics->campaignKpi($c, now()->subDays(30))->toArray(),
            ]),
        ]);
    }
}
