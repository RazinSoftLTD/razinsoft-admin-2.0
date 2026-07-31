<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generate due recurring invoices every day.
Schedule::command('invoices:recurring')->dailyAt('06:00');

// Permanently purge invoices & clients sitting in the Bin for more than 30 days.
Schedule::command('invoices:purge-bin')->dailyAt('03:00');
Schedule::command('clients:purge-bin')->dailyAt('03:10');
Schedule::command('projects:purge-bin')->dailyAt('03:20');

// Envato gives no sales history, so this run is what records it for the CodeCanyon dashboard.
//
// Hourly rather than daily for two reasons: sales land throughout the day and a
// figure that is up to 24h old reads as broken next to the real CodeCanyon page,
// and a run that fails now simply retries an hour later instead of forfeiting the
// day's snapshot — which could never be recovered, since Envato serves only
// today's numbers. Each run overwrites today's snapshot row, so the history stays
// one row per day and the last reading before midnight is the day's close.
Schedule::command('codecanyon:sync --now')->hourly()->withoutOverlapping();
