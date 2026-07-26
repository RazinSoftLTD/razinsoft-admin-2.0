<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\InvoicePayController;
use App\Http\Controllers\PublicPrdController;

Route::get('/', function () {
    return view('welcome');
});

// ---- Public invoice pay (token-guarded, no login). The page lives on the frontend;
//      these backend routes redirect there + handle Stripe checkout/recording. ----
Route::get('invoice/pay/{token}', [InvoicePayController::class, 'show'])->name('pay.invoice.show');
Route::get('invoice/pay/{token}/checkout', [InvoicePayController::class, 'checkout'])->name('pay.invoice.checkout');
Route::get('invoice/pay/{token}/success', [InvoicePayController::class, 'success'])->name('pay.invoice.success');
Route::get('invoice/pay/{token}/paypal', [InvoicePayController::class, 'paypal'])->name('pay.invoice.paypal');
Route::get('invoice/pay/{token}/paypal/return', [InvoicePayController::class, 'paypalReturn'])->name('pay.invoice.paypal.return');

// ---- Client-facing PRD (token-guarded, no login). Clients submit only; review stays in the panel. ----
Route::get('prd/{token}', [PublicPrdController::class, 'show'])->name('prd.public');
Route::post('prd/{token}', [PublicPrdController::class, 'store'])->name('prd.public.store');

// ---- Email tracking (no login: these are opened by the recipient's mail client / browser).
//      Each message carries an unguessable UUID; that is the only thing identifying it. ----
Route::get('email/track/open/{tracking}', [\App\Http\Controllers\EmailTrackingController::class, 'open'])->name('email.track.open');
Route::get('email/track/click/{tracking}', [\App\Http\Controllers\EmailTrackingController::class, 'click'])->name('email.track.click');
Route::get('email/unsubscribe/{tracking}', [\App\Http\Controllers\EmailTrackingController::class, 'unsubscribe'])->name('email.unsubscribe');
