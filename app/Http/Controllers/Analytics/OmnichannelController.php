<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\Analytics\OmnichannelService;
use Inertia\Inertia;
use Inertia\Response;

class OmnichannelController extends Controller
{
    public function index(OmnichannelService $omni): Response
    {
        $journeys = Contact::latest('id')->limit(25)->get()
            ->map(fn (Contact $c) => $omni->journey($c) + ['name' => $c->fullName()]);

        return Inertia::render('analytics/omnichannel', [
            'channels' => $omni->channels(),
            'journeys' => $journeys,
        ]);
    }
}
