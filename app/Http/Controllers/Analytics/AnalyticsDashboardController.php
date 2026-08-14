<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsDashboardController extends Controller
{
    public function __invoke(AnalyticsService $analytics): Response
    {
        return Inertia::render('analytics/dashboard', [
            'metrics' => $analytics->dashboard(),
        ]);
    }
}
