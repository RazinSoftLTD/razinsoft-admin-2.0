<?php

/*
|--------------------------------------------------------------------------
| Maps lead dashboard routes
|--------------------------------------------------------------------------
| Included from routes/admin.php, which is already loaded inside the `web`
| middleware group by bootstrap/app.php - so `web` is not repeated here.
|
| Gating matches the rest of the admin area: `staff` + `log.activity`, an
| `admin/` URL prefix and an `admin.` route-name prefix.
|
| Note the naming: these routes serve the Google Maps collector's own
| maps_leads table. The CRM's `leads` table and Admin\LeadController are a
| completely separate feature and are untouched by this module.
*/

use App\Http\Controllers\MapsLeadDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['staff', 'log.activity'])
    ->group(function () {
        Route::get('maps-leads', [MapsLeadDashboardController::class, 'index'])->name('maps-leads.dashboard');
        Route::get('maps-leads/runs', [MapsLeadDashboardController::class, 'runs'])->name('maps-leads.runs');
        Route::get('maps-leads/export/csv', [MapsLeadDashboardController::class, 'exportCsv'])->name('maps-leads.export.csv');
        Route::patch('maps-leads/{lead}', [MapsLeadDashboardController::class, 'update'])->name('maps-leads.update');
    });

/*
| Public opt-out for outreach messages. No auth and no `admin` prefix: it is
| opened from an email by a recipient who has no account here. The random token
| is the authorisation, and the action only ever adds to the suppression list.
*/
Route::get('outreach/unsubscribe/{token}', \App\Http\Controllers\OutreachUnsubscribeController::class)
    ->name('outreach.unsubscribe');
