<?php

use App\Authorization\Role;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Contact;
use App\Models\Organization;
use App\Services\Sales\BookingService;
use App\Support\CurrentOrganization;

function bookingPage(Organization $org, array $overrides = []): BookingPage
{
    app(CurrentOrganization::class)->set($org);
    $page = BookingPage::create(array_merge([
        'name' => 'Consultation', 'slug' => 'consult-'.$org->id, 'meeting_type' => 'consultation',
        'duration_minutes' => 30, 'assignment' => 'fixed', 'is_active' => true,
    ], $overrides));
    app(CurrentOrganization::class)->forget();

    return $page;
}

it('books publicly and creates a booking + contact + activity', function () {
    [$org, $owner] = salesOrganization();
    bookingPage($org, ['slug' => 'book-me', 'user_id' => $owner->id]);

    $this->post('/b/book-me', ['name' => 'Jane', 'email' => 'jane@x.com', 'scheduled_at' => now()->addDay()->toDateTimeString()])->assertOk();

    $booking = Booking::withoutGlobalScope('tenant')->firstWhere('email', 'jane@x.com');
    expect($booking)->not->toBeNull()
        ->and($booking->organization_id)->toBe($org->id)
        ->and($booking->owner_id)->toBe($owner->id)
        ->and($booking->status)->toBe('booked');

    $contact = Contact::withoutGlobalScope('tenant')->firstWhere('email', 'jane@x.com');
    expect($contact)->not->toBeNull()->and($contact->lead_source)->toBe('booking');
    expect(Activity::withoutGlobalScope('tenant')->where('type', 'meeting')->where('subject_id', $contact->id)->exists())->toBeTrue();
});

it('dedupes the contact by email when booking', function () {
    [$org] = salesOrganization();
    app(CurrentOrganization::class)->set($org);
    Contact::create(['first_name' => 'Existing', 'email' => 'dup@x.com']);
    app(CurrentOrganization::class)->forget();
    bookingPage($org, ['slug' => 'dedupe']);

    $this->post('/b/dedupe', ['name' => 'Dup', 'email' => 'dup@x.com', 'scheduled_at' => now()->addDay()->toDateTimeString()])->assertOk();

    expect(Contact::withoutGlobalScope('tenant')->where('email', 'dup@x.com')->count())->toBe(1);
});

it('round-robins the owner across active members', function () {
    [$org] = salesOrganization();
    addMember($org, Role::SalesRepresentative);
    addMember($org, Role::SalesRepresentative);
    $page = bookingPage($org, ['slug' => 'rr', 'assignment' => 'round_robin']);

    app(CurrentOrganization::class)->set($org);
    $service = app(BookingService::class);
    $first = $service->book($page, ['name' => 'A', 'email' => 'a@x.com', 'scheduled_at' => now()->addDay()]);
    $second = $service->book($page, ['name' => 'B', 'email' => 'b@x.com', 'scheduled_at' => now()->addDay()]);
    app(CurrentOrganization::class)->forget();

    expect($first->owner_id)->not->toBeNull()
        ->and($second->owner_id)->not->toBeNull()
        ->and($first->owner_id)->not->toBe($second->owner_id); // least-loaded rotates
});

it('404s an inactive booking page', function () {
    [$org] = salesOrganization();
    bookingPage($org, ['slug' => 'inactive', 'is_active' => false]);

    $this->get('/b/inactive')->assertNotFound();
});
