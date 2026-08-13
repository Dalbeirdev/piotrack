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
