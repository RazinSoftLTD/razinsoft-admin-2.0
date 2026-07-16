<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\InvoicePayController;

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
