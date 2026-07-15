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
