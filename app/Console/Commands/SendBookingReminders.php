<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingReminderNotification;
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

    public function handle(NotificationDispatcher $notifications): int
    {
        $sent = 0;

        Booking::query()
            ->where('status', 'booked')
            ->whereBetween('scheduled_at', [now(), now()->addDay()])
            ->whereNotNull('owner_id')
            ->each(function (Booking $booking) use ($notifications, &$sent) {
                $owner = User::find($booking->owner_id);

                if ($owner !== null) {
                    $notifications->toUser($owner, new BookingReminderNotification($booking->name, $booking->scheduled_at->toDayDateTimeString()));
                    $sent++;
                }
            });

        $this->components->info("Sent {$sent} booking reminder(s).");

        return self::SUCCESS;
    }
}
