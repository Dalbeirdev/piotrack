<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Form;
use App\Models\MarketingList;
use App\Models\Workflow;
use Inertia\Inertia;
use Inertia\Response;

class MarketingDashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('marketing/dashboard', [
            'stats' => [
                'lists' => MarketingList::count(),
                'forms' => Form::count(),
                'campaigns' => Campaign::count(),
                'workflows' => Workflow::where('status', 'active')->count(),
                'contacts' => Contact::count(),
                'leads' => Contact::where('lifecycle_stage', 'lead')->count(),
            ],
            'lifecycle' => Contact::query()
                ->selectRaw('lifecycle_stage, count(*) as total')
                ->groupBy('lifecycle_stage')
                ->pluck('total', 'lifecycle_stage'),
            'recentCampaigns' => Campaign::latest('id')->limit(5)->get()
                ->map(fn (Campaign $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'channel' => $c->channel,
                    'status' => $c->status,
                    'sent' => $c->stat_sent,
                    'opened' => $c->stat_opened,
                    'clicked' => $c->stat_clicked,
                ]),
        ]);
    }
}
