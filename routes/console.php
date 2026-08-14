<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Billing lifecycle sweeps (Stage 4 JOBS — closes BILL-011/012/016/017).
// Run frequently so period boundaries are honored promptly; each command is
// idempotent (it only acts on subscriptions past their boundary).
Schedule::command('subscriptions:process-renewals')->hourly()->withoutOverlapping();
Schedule::command('subscriptions:expire-trials')->hourly()->withoutOverlapping();
Schedule::command('subscriptions:enforce-grace')->hourly()->withoutOverlapping();
Schedule::command('subscriptions:notify-trial-ending')->dailyAt('09:00');

// Marketing execution (Stage 6). Workflow drip delays and scheduled campaigns
// are advanced frequently; each command dispatches per-tenant queued jobs.
Schedule::command('marketing:process-workflows')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('marketing:send-scheduled-campaigns')->everyFiveMinutes()->withoutOverlapping();

// Advertising metrics refresh (Stage 8) — pull daily performance for active
// campaigns; each dispatches per-tenant queued jobs.
Schedule::command('ads:refresh-metrics')->dailyAt('06:00')->withoutOverlapping();
