<?php

declare(strict_types=1);

/**
 * QA §24 - appointment booking, from the public page.
 *
 * The defect this covers: /b/{slug} told the person who booked "Your :type is
 * confirmed. We will send a reminder." Booking sent no mail at all, and the
 * reminder command notified only the owner, through an in-app notification
 * linking to /sales/booking - an authenticated route the attendee cannot open.
 * The promise made in writing to an external party was never kept, and a
 * prospect relying on that reminder would simply miss the meeting.
 *
 * §24 also names double booking and "no availability". Both are genuinely
 * unimplemented, and correctly recorded as such: BOOK-003 Salesperson
 * availability and BOOK-001 Calendar integration are Planned. The behaviour is
 * pinned here so the register stays honest rather than drifting.
 */

use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Contact;
use App\Models\OutboundMessage;
use App\Services\Sales\BookingService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);

    $this->page = BookingPage::create([
        'name' => 'CMMC readiness assessment',
        'slug' => 'cmmc-assessment',
        'meeting_type' => 'consultation',
        'duration_minutes' => 30,
        'assignment' => 'round_robin',
        'availability' => ['mon' => ['09:00', '17:00'], 'tue' => ['09:00', '17:00']],
        'is_active' => true,
    ]);

    app(CurrentOrganization::class)->forget();
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('emails a confirmation to the person who booked', function () {
    $this->post('/b/cmmc-assessment', [
        'name' => 'Michael Rodriguez',
        'email' => 'michael.rodriguez@precisionmfg-test.com',
        'scheduled_at' => now()->addDays(3)->setTime(14, 0)->toDateTimeString(),
    ])->assertSuccessful();

    app(CurrentOrganization::class)->set($this->org);

    $booking = Booking::firstOrFail();
    $confirmation = OutboundMessage::where('address', 'michael.rodriguez@precisionmfg-test.com')->first();

    expect($confirmation)->not->toBeNull('the attendee was never told anything')
        ->and($confirmation->source)->toBe('booking')
        ->and($confirmation->subject)->toContain('confirmed')
        ->and($booking->contact_id)->not->toBeNull();
});

it('reminds the attendee, not only the sales rep', function () {
    $this->post('/b/cmmc-assessment', [
        'name' => 'Michael Rodriguez',
        'email' => 'michael.rodriguez@precisionmfg-test.com',
        'scheduled_at' => now()->addHours(12)->toDateTimeString(),
    ])->assertSuccessful();

    app(CurrentOrganization::class)->set($this->org);
    $before = OutboundMessage::count();
    app(CurrentOrganization::class)->forget();

    $this->artisan('sales:send-booking-reminders')->assertSuccessful();

    app(CurrentOrganization::class)->set($this->org);

    $reminder = OutboundMessage::where('address', 'michael.rodriguez@precisionmfg-test.com')
        ->where('subject', 'like', 'Reminder%')->first();

    expect(OutboundMessage::count())->toBeGreaterThan($before)
        ->and($reminder)->not->toBeNull('the attendee got no reminder')
        ->and($reminder->source)->toBe('booking');
});

it('creates a booking, a deduped contact and a CRM activity', function () {
    foreach ([1, 2] as $i) {
        $this->post('/b/cmmc-assessment', [
            'name' => 'Michael Rodriguez',
            'email' => 'michael.rodriguez@precisionmfg-test.com',
            'scheduled_at' => now()->addDays($i)->toDateTimeString(),
        ])->assertSuccessful();
    }

    app(CurrentOrganization::class)->set($this->org);

    expect(Booking::count())->toBe(2)
        // The same person booking twice is one contact.
        ->and(Contact::where('email', 'michael.rodriguez@precisionmfg-test.com')->count())->toBe(1)
        ->and(DB::table('activities')->where('type', 'meeting')->count())->toBe(2);

    expect(Booking::first()->owner_id)->not->toBeNull('round-robin assigned nobody');
});

it('refuses a booking in the past', function () {
    $this->post('/b/cmmc-assessment', [
        'name' => 'Michael Rodriguez',
        'email' => 'michael.rodriguez@precisionmfg-test.com',
        'scheduled_at' => now()->subDay()->toDateTimeString(),
    ])->assertSessionHasErrors('scheduled_at');

    app(CurrentOrganization::class)->set($this->org);
    expect(Booking::count())->toBe(0);
});

it('supports cancellation and rescheduling', function () {
    $this->post('/b/cmmc-assessment', [
        'name' => 'Michael Rodriguez',
        'email' => 'michael.rodriguez@precisionmfg-test.com',
        'scheduled_at' => now()->addDays(3)->toDateTimeString(),
    ])->assertSuccessful();

    app(CurrentOrganization::class)->set($this->org);
    $booking = Booking::firstOrFail();
    $service = app(BookingService::class);

    $moved = now()->addDays(5)->startOfHour();
    $service->reschedule($booking, $moved);

    expect($booking->fresh()->scheduled_at->toDateTimeString())->toBe($moved->toDateTimeString());

    $service->setStatus($booking->fresh(), 'cancelled');

    expect($booking->fresh()->status)->toBe('cancelled');

    // A cancelled meeting must not generate reminders.
    app(CurrentOrganization::class)->forget();
    $this->artisan('sales:send-booking-reminders')->assertSuccessful();

    app(CurrentOrganization::class)->set($this->org);
    expect(OutboundMessage::where('subject', 'like', 'Reminder%')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| §24 cases that are genuinely unimplemented - pinned, not papered over
|--------------------------------------------------------------------------
*/

it('does not yet enforce availability or prevent double booking', function () {
    // booking_pages.availability is stored and cast, and read by nothing:
    // BookingService::book() accepts any future time and never checks for a
    // clashing booking on the same owner. BOOK-003 (Salesperson availability)
    // and BOOK-001 (Calendar integration) are Planned, so this documents the
    // current behaviour rather than asserting it is correct. When slots land,
    // this test fails and must be replaced by the real availability assertions.
    $slot = now()->addDays(3)->setTime(3, 0)->toDateTimeString(); // 3am, outside availability

    foreach (['first@precisionmfg-test.com', 'second@precisionmfg-test.com'] as $email) {
        $this->post('/b/cmmc-assessment', [
            'name' => 'Prospect', 'email' => $email, 'scheduled_at' => $slot,
        ])->assertSuccessful();
    }

    app(CurrentOrganization::class)->set($this->org);

    $bookings = Booking::where('scheduled_at', $slot)->get();

    expect($bookings)->toHaveCount(2)
        ->and($bookings->pluck('owner_id')->unique())->toHaveCount(1, 'both went to the same rep');

    expect($this->page->fresh()->availability)->not->toBeNull('availability is configured but ignored');
});
