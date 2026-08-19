<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingReminderNotification;
use App\Services\Marketing\MessageDispatcher;
use App\Support\CurrentOrganization;
use App\Support\NotificationDispatcher;
use Illuminate\Console\Command;

/**
 * Reminds booking owners about meetings in the next 24 hours (BOOK-007). Runs
 * across all tenants (bookings are unscoped in the console); each reminder goes
 * to the assigned owner. Scheduled once daily so a booking is reminded ~once.
 */
class SendBookingReminders extends Command
{
    protected $signature = 'sales:send-booking-reminders';

    protected $description = 'Notify owners of bookings scheduled in the next 24 hours';

    public function handle(NotificationDispatcher $notifications, MessageDispatcher $messages, CurrentOrganization $current): int
    {
        $sent = 0;

        Booking::query()
            ->where('status', 'booked')
            ->whereBetween('scheduled_at', [now(), now()->addDay()])
            ->whereNotNull('owner_id')
            ->each(function (Booking $booking) use ($notifications, $messages, $current, &$sent) {
                $when = $booking->scheduled_at->toDayDateTimeString();
                $owner = User::find($booking->owner_id);

                if ($owner !== null) {
                    $notifications->toUser($owner, new BookingReminderNotification($booking->name, $when));
                    $sent++;
                }

                // The attendee is the one the public page promised a reminder
                // to. The owner's in-app notification points at an
                // authenticated route they cannot open.
                // Mail is written per tenant, so the booking's own organization
                // has to be the active context - the scheduler runs with none.
                $organization = $booking->organization()->first();
                $contact = $booking->contact()->first();

                if ($organization !== null && $contact !== null && $contact->email !== null) {
                    $current->set($organization);
                    $messages->sendEmail(
                        $contact,
                        __('Reminder: your meeting on :when', ['when' => $when]),
                        __('This is a reminder of your meeting on :when (UTC).', ['when' => $when]),
                        'booking',
                    );

                    $current->forget();
                }
            });

        $this->components->info("Sent {$sent} booking reminder(s).");

        return self::SUCCESS;
    }
}
