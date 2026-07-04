<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\InvoicePayController;

Route::get('/', function () {
    return view('welcome');
});

// ---- Public invoice pay (token-guarded, no login) ----
Route::get('invoice/pay/{token}', [InvoicePayController::class, 'show'])->name('pay.invoice.show');
Route::post('invoice/pay/{token}/checkout', [InvoicePayController::class, 'checkout'])->name('pay.invoice.checkout');
Route::get('invoice/pay/{token}/success', [InvoicePayController::class, 'success'])->name('pay.invoice.success');
