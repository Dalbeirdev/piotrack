<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Services\Sales\BookingService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $booking,
        private CurrentOrganization $currentOrganization,
    ) {}

    public function index(): Response
    {
        return Inertia::render('sales/booking/index', [
            'pages' => BookingPage::with('owner:id,name')->latest('id')->get()->map(fn (BookingPage $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'meeting_type' => $p->meeting_type,
                'duration_minutes' => $p->duration_minutes,
                'assignment' => $p->assignment,
                'is_active' => $p->is_active,
                'owner' => $p->owner?->name,
                'public_url' => url("/b/{$p->slug}"),
            ]),
            'bookings' => Booking::with('page:id,name')->latest('id')->limit(100)->get()->map(fn (Booking $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'email' => $b->email,
                'page' => $b->page?->name,
                'scheduled_at' => $b->scheduled_at->toIso8601String(),
                'status' => $b->status,
            ]),
            'members' => $this->memberOptions(),
        ]);
    }

    public function storePage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'meeting_type' => ['required', 'string', 'max:60'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'assignment' => ['required', Rule::in(['fixed', 'round_robin'])],
            'user_id' => ['nullable', Rule::exists('organization_user', 'user_id')->where('organization_id', $this->currentOrganization->id())],
        ]);
        $data['slug'] = $this->uniqueSlug($data['name']);

        BookingPage::create($data);

        return back()->with('status', __('Booking page created.'));
    }

    public function publishPage(BookingPage $bookingPage): RedirectResponse
    {
        $bookingPage->update(['is_active' => ! $bookingPage->is_active]);

        return back()->with('status', __('Booking page updated.'));
    }

    public function destroyPage(BookingPage $bookingPage): RedirectResponse
    {
        $bookingPage->delete();

        return back()->with('status', __('Booking page removed.'));
    }

    public function bookingStatus(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['booked', 'completed', 'canceled', 'no_show'])]]);
        $this->booking->setStatus($booking, $data['status']);

        return back()->with('status', __('Booking :status.', ['status' => $data['status']]));
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function memberOptions(): array
    {
        $organization = $this->currentOrganization->get();

        if ($organization === null) {
            return [];
        }

        return $organization->members()->orderBy('name')->get(['users.id', 'users.name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'book';
        $slug = $base;
        $i = 1;

        while (BookingPage::withoutGlobalScope('tenant')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
