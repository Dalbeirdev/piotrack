<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\CallTrackingNumber;
use App\Services\Analytics\CallTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CallController extends Controller
{
    public function __construct(private CallTrackingService $calls) {}

    public function index(): Response
    {
        return Inertia::render('analytics/calls', [
            'numbers' => CallTrackingNumber::latest('id')->get()->map(fn (CallTrackingNumber $n) => [
                'id' => $n->id,
                'phone_number' => $n->phone_number,
                'label' => $n->label,
                'source' => $n->source,
                'campaign' => $n->campaign,
                'is_active' => $n->is_active,
            ]),
            'calls' => Call::with('contact:id,first_name,last_name')->latest('id')->limit(100)->get()
                ->map(fn (Call $c) => [
                    'id' => $c->id,
                    'from_number' => $c->from_number,
                    'direction' => $c->direction,
                    'duration_seconds' => $c->duration_seconds,
                    'status' => $c->status,
                    'source' => $c->source,
                    'campaign' => $c->campaign,
                    'score' => $c->score,
                    'is_qualified' => $c->is_qualified,
                    'converted' => $c->converted,
                    'contact' => $c->contact?->fullName(),
                    'occurred_at' => $c->occurred_at?->toIso8601String(),
                ]),
            'breakdown' => $this->calls->sourceBreakdown(),
        ]);
    }

    public function storeNumber(Request $request): RedirectResponse
    {
        $this->calls->provisionNumber($request->validate([
            'label' => ['nullable', 'string', 'max:150'],
            'source' => ['required', 'string', 'max:100'],
            'campaign' => ['nullable', 'string', 'max:150'],
        ]));

        return back()->with('status', __('Tracking number provisioned.'));
    }

    public function storeCall(Request $request): RedirectResponse
    {
        $this->calls->logCall($request->validate([
            'call_tracking_number_id' => ['nullable', 'integer', 'exists:call_tracking_numbers,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'from_number' => ['nullable', 'string', 'max:32'],
            'direction' => ['required', Rule::in(['inbound', 'outbound'])],
            'duration_seconds' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['completed', 'missed', 'voicemail'])],
            'converted' => ['boolean'],
        ]));

        return back()->with('status', __('Call logged.'));
    }

    public function convert(Call $call): RedirectResponse
    {
        $this->calls->markConverted($call);

        return back()->with('status', __('Call marked as converted.'));
    }

    public function destroyNumber(CallTrackingNumber $number): RedirectResponse
    {
        $number->delete();

        return back()->with('status', __('Tracking number removed.'));
    }
}
