<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\ContentPiece;
use App\Models\OutreachProspect;
use App\Models\SocialPost;
use App\Services\Content\ReputationService;
use Inertia\Inertia;
use Inertia\Response;

class ContentDashboardController extends Controller
{
    public function __construct(private ReputationService $reputation) {}

    public function __invoke(): Response
    {
        return Inertia::render('content/dashboard', [
            'stats' => [
                'pieces' => ContentPiece::count(),
                'published' => ContentPiece::where('status', 'published')->count(),
                'social_posts' => SocialPost::count(),
                'placements' => OutreachProspect::where('status', 'won')->whereNotNull('placement_url')->count(),
            ],
            'byStatus' => ContentPiece::query()
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'reviews' => $this->reputation->aggregate(),
            'recentPieces' => ContentPiece::latest('id')->limit(6)->get()
                ->map(fn (ContentPiece $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'content_type' => $p->content_type,
                    'status' => $p->status,
                    'optimization_score' => $p->optimization_score,
                ]),
        ]);
    }
}
