<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// ---- Auth (public) ----
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// ---- Products (public) ----
Route::get('/products', [ProductController::class, 'index']);
Route::get('/categories', [ProductController::class, 'categories']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

// ---- Payment webhooks (public, no auth) ----
Route::post('/webhooks/stripe', [WebhookController::class, 'stripe']);
Route::post('/webhooks/paypal', [WebhookController::class, 'paypal']);
Route::get('/dev/pay/{order}', [WebhookController::class, 'devPay']); // local-only

// ---- Authenticated ----
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::post('/products/{slug}/questions', [ProductController::class, 'storeQuestion']);
    Route::post('/products/{slug}/questions/{question}/answers', [ProductController::class, 'storeAnswer']);

    Route::post('/products/{slug}/reviews', [ProductController::class, 'storeReview']);

    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::post('/orders/{orderNumber}/repay', [CheckoutController::class, 'repay']);
    Route::post('/orders/{orderNumber}/confirm', [CheckoutController::class, 'confirm']);

    // ---- Account (customer area) ----
    Route::get('/account/dashboard', [AccountController::class, 'dashboard']);
    Route::get('/account/orders', [AccountController::class, 'orders']);
    Route::get('/account/orders/{orderNumber}', [AccountController::class, 'order']);
    Route::get('/account/invoices/{invoice}/download', [AccountController::class, 'downloadInvoice'])->name('account.invoice.download');
    Route::get('/account/licenses/{license}/download', [AccountController::class, 'downloadLicense'])->name('account.license.download');

    // gated source download — temporary signed URL + auth + ownership check
    Route::get('/account/products/{product}/source', [AccountController::class, 'downloadSource'])
        ->middleware('signed')->name('account.source.download');
});
