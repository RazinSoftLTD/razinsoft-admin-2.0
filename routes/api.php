<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// ---- Auth (public) ----
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// ---- Products (public) ----
Route::get('/products', [ProductController::class, 'index']);
Route::get('/categories', [ProductController::class, 'categories']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

// ---- Insights / Blog (public) ----
Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{slug}', [ArticleController::class, 'show']);

// ---- Contact form (public) ----
Route::post('/contact', [\App\Http\Controllers\Api\ContactController::class, 'store']);

// ---- Book a Meeting (public calendar) ----
Route::get('/meetings/config', [\App\Http\Controllers\Api\MeetingController::class, 'config']);
Route::get('/meetings/availability', [\App\Http\Controllers\Api\MeetingController::class, 'availability']);
Route::post('/meetings/book', [\App\Http\Controllers\Api\MeetingController::class, 'book']);

// ---- Blog "Follow" subscription (public) ----
Route::post('/subscribe', [\App\Http\Controllers\Api\SubscriberController::class, 'store']);

// Client page-visit beacon — logs only when a valid client token is present (checked in the controller).
Route::post('/track/visit', [\App\Http\Controllers\Api\TrackController::class, 'visit'])->middleware('throttle:120,1');

// ---- Search analytics (public) ----
Route::post('/search-log', [\App\Http\Controllers\Api\SearchController::class, 'store']);

// ---- Public invoice pay page data (token-guarded, no auth) — consumed by the frontend pay page ----
Route::get('/invoice/pay/{token}', [\App\Http\Controllers\InvoicePayController::class, 'apiShow']);

// ---- Payment webhooks (public, no auth) ----
Route::post('/webhooks/stripe', [WebhookController::class, 'stripe']);
Route::post('/webhooks/paypal', [WebhookController::class, 'paypal']);
Route::get('/dev/pay/{order}', [WebhookController::class, 'devPay']); // local-only

// ---- Email verification link (signed, opened from the email — no auth token in the browser) ----
Route::get('/account/email/verify/{id}/{hash}', [AccountController::class, 'verifyEmail'])
    ->middleware('signed')->name('account.email.verify');

// ---- Authenticated ----
Route::middleware(['auth:sanctum', 'client.active'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Support tickets — reachable even for inactive accounts (support-only path).
    Route::get('/support/tickets', [\App\Http\Controllers\Api\SupportTicketController::class, 'index']);
    Route::post('/support/tickets', [\App\Http\Controllers\Api\SupportTicketController::class, 'store']);
    Route::get('/support/tickets/{ticket}', [\App\Http\Controllers\Api\SupportTicketController::class, 'show'])->whereNumber('ticket');
    Route::post('/support/tickets/{ticket}/replies', [\App\Http\Controllers\Api\SupportTicketController::class, 'reply'])->whereNumber('ticket');

    Route::post('/products/{slug}/questions', [ProductController::class, 'storeQuestion']);
    Route::post('/products/{slug}/questions/{question}/answers', [ProductController::class, 'storeAnswer']);

    Route::post('/products/{slug}/reviews', [ProductController::class, 'storeReview']);

    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::post('/orders/{orderNumber}/repay', [CheckoutController::class, 'repay']);
    Route::post('/orders/{orderNumber}/confirm', [CheckoutController::class, 'confirm']);

    // ---- Account (customer area) ----
    Route::get('/account/dashboard', [AccountController::class, 'dashboard']);
    Route::get('/account/meetings', [AccountController::class, 'meetings']);

    // Direct messages with the RazinSoft team
    Route::get('/account/messages', [\App\Http\Controllers\Api\AccountMessageController::class, 'index']);
    Route::post('/account/messages', [\App\Http\Controllers\Api\AccountMessageController::class, 'store']);

    // Profile management
    Route::put('/account/profile', [AccountController::class, 'updateProfile']);
    Route::put('/account/password', [AccountController::class, 'updatePassword']);
    Route::post('/account/avatar', [AccountController::class, 'updateAvatar']);
    Route::post('/account/email/verify', [AccountController::class, 'sendEmailVerification']);
    Route::delete('/account', [AccountController::class, 'destroy']);

    Route::get('/account/orders', [AccountController::class, 'orders']);
    Route::get('/account/orders/{orderNumber}', [AccountController::class, 'order']);
    Route::get('/account/invoices', [AccountController::class, 'invoices']);
    Route::get('/account/invoices/{invoice}/download', [AccountController::class, 'downloadInvoice'])->name('account.invoice.download');
    Route::get('/account/licenses/{license}/download', [AccountController::class, 'downloadLicense'])->name('account.license.download');

    // gated source download — temporary signed URL + auth + ownership check
    Route::get('/account/products/{product}/source', [AccountController::class, 'downloadSource'])
        ->middleware('signed')->name('account.source.download');
});
