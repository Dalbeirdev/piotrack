<?php

namespace App\Http\Controllers;

use App\Services\OnboardingChecklist;
use App\Support\CurrentOrganization;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(CurrentOrganization $currentOrganization, OnboardingChecklist $checklist): Response
    {
        return Inertia::render('dashboard', [
            'onboarding' => $checklist->for($currentOrganization->get()),
        ]);
    }
}
