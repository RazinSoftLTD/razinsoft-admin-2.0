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

// Envato gives no sales history — this daily run is what records it for the CodeCanyon dashboard.
Schedule::command('codecanyon:sync')->dailyAt('04:00')->withoutOverlapping();
