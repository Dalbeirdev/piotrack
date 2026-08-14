<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\SalesAlert;
use App\Models\TargetAccount;
use App\Services\Sales\LeadScoringService;
use Inertia\Inertia;
use Inertia\Response;

class SalesDashboardController extends Controller
{
    public function __construct(private LeadScoringService $scoring) {}

    public function __invoke(): Response
    {
        $contacts = Contact::query()->get(['id', 'lead_score']);

        return Inertia::render('sales/dashboard', [
            'temperature' => [
                'hot' => $contacts->filter(fn ($c) => $this->scoring->temperature($c->lead_score) === 'hot')->count(),
                'warm' => $contacts->filter(fn ($c) => $this->scoring->temperature($c->lead_score) === 'warm')->count(),
                'cold' => $contacts->filter(fn ($c) => $this->scoring->temperature($c->lead_score) === 'cold')->count(),
            ],
            'stats' => [
                'unread_alerts' => SalesAlert::where('is_read', false)->count(),
                'upcoming_bookings' => Booking::where('status', 'booked')->where('scheduled_at', '>=', now())->count(),
                'target_accounts' => TargetAccount::count(),
            ],
            'recentAlerts' => SalesAlert::with('contact:id,first_name,last_name')->latest('id')->limit(6)->get()
                ->map(fn (SalesAlert $a) => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'message' => $a->message,
                    'is_read' => $a->is_read,
                    'contact' => $a->contact?->fullName(),
                ]),
        ]);
    }
}
