<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\GrowthScoreService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GrowthScoreController extends Controller
{
    public function index(GrowthScoreService $scores): Response
    {
        $computed = $scores->compute();

        return Inertia::render('analytics/growth-score', [
            'overall' => $computed['overall'],
            'breakdown' => $computed['breakdown'],
            'recommendations' => $computed['recommendations'],
            'weights' => GrowthScoreService::WEIGHTS,
            'trend' => $scores->trend(),
        ]);
    }

    public function snapshot(GrowthScoreService $scores): RedirectResponse
    {
        $scores->snapshot();

        return back()->with('status', __('Growth score snapshot saved.'));
    }
}
