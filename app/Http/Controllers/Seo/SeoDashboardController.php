<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\AiVisibilityCheck;
use App\Models\Keyword;
use App\Models\SeoAudit;
use App\Models\SeoLocation;
use App\Services\Seo\RankTracker;
use Inertia\Inertia;
use Inertia\Response;

class SeoDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $trackedKeywords = Keyword::where('is_tracked', true)->get();

        return Inertia::render('seo/dashboard', [
            'stats' => [
                'audits' => SeoAudit::count(),
                'avg_score' => (int) round(SeoAudit::avg('score') ?? 0),
                'keywords' => Keyword::count(),
                'page_one' => $trackedKeywords->filter(fn (Keyword $k) => RankTracker::isPageOne($k->current_position))->count(),
                'top_three' => $trackedKeywords->filter(fn (Keyword $k) => RankTracker::isTopThree($k->current_position))->count(),
                'locations' => SeoLocation::count(),
                'ai_checks' => AiVisibilityCheck::count(),
            ],
            'recentAudits' => SeoAudit::latest('id')->limit(5)->get()
                ->map(fn (SeoAudit $a) => ['id' => $a->id, 'url' => $a->url, 'score' => $a->score, 'issues_count' => $a->issues_count]),
        ]);
    }
}
