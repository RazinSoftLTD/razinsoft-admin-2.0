<?php

/*
|--------------------------------------------------------------------------
| Maps collector API routes
|--------------------------------------------------------------------------
| Include this from routes/api.php:
|
|     require __DIR__.'/api-leads.php';
|
| Everything sits behind auth:sanctum. The ingest route gets its own, tighter
| throttle: the extension backs off on 429 automatically, so a limit here is a
| safety net rather than a failure mode.
|
| The `leads:write` ability is also required. This matters because the token
| lives in the extension's chrome.storage.local as plain text: without an
| ability check, that one string would also open every other auth:sanctum route
| in this app (account deletion, password change, checkout, source downloads).
| Mint the collector's token with
|
|     $user->createToken('maps-collector', ['leads:write'])
|
| and it can reach nothing but the routes below. Tokens created with the default
| ['*'] abilities keep working, so this is backwards compatible.
|
| CheckForAnyAbility is referenced by class on purpose: Sanctum's 'abilities'
| middleware alias is not registered by default, and requiring an edit to
| bootstrap/app.php would be an easy step to miss.
*/

use App\Http\Controllers\Api\V1\MapsLeadController;
use App\Http\Controllers\Api\V1\MapsLeadIngestController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

Route::prefix('v1')
    ->middleware(['auth:sanctum', CheckForAnyAbility::class.':leads:write'])
    ->group(function () {
        // --- ingest (used by the Chrome extension) ----------------------
        Route::post('leads/store', [MapsLeadIngestController::class, 'store'])
            ->middleware('throttle:180,1')
            ->name('api.leads.store');

        Route::get('leads/runs', [MapsLeadIngestController::class, 'runs'])->name('api.leads.runs');

        // --- lead management --------------------------------------------
        Route::get('leads/export/csv', [MapsLeadController::class, 'exportCsv'])->name('api.leads.export.csv');
        Route::get('leads/export/xlsx', [MapsLeadController::class, 'exportXlsx'])->name('api.leads.export.xlsx');
        Route::get('leads/logs', [MapsLeadController::class, 'logs'])->name('api.leads.logs');

        Route::get('leads', [MapsLeadController::class, 'index'])->name('api.leads.index');
        Route::get('leads/{lead}', [MapsLeadController::class, 'show'])->name('api.leads.show');
        Route::patch('leads/{lead}', [MapsLeadController::class, 'update'])->name('api.leads.update');
        Route::delete('leads/{lead}', [MapsLeadController::class, 'destroy'])->name('api.leads.destroy');
    });
