<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AccountMessageController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AttendanceDeviceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingAddressController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\EmailWebhookController;
use App\Http\Controllers\Api\InstallationPlanController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SubscriberController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\TrackController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\WhatsappGatewayController;
use App\Http\Controllers\Api\WhatsappWebhookController;
use App\Http\Controllers\InvoicePayController;
use App\Http\Controllers\ProductLinkController;
use App\Models\BrandSetting;
use Illuminate\Support\Facades\Route;

// ---- Auth (public) ----
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// ---- Products (public) ----
Route::get('/installation-plans', [InstallationPlanController::class, 'index']);

// Biometric sync bridge: posts device punches with the device's API token (see HR > Devices).
Route::post('/attendance/device-logs', [AttendanceDeviceController::class, 'push']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/categories', [ProductController::class, 'categories']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

// ---- Insights / Blog (public) ----
Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{slug}', [ArticleController::class, 'show']);

Route::get('/jobs', [JobController::class, 'index']);
Route::get('/promotion/active', [PromotionController::class, 'active']);

// ---- Contact form (public) ----
Route::post('/contact', [ContactController::class, 'store']);

// ---- Book a Meeting (public calendar) ----
Route::get('/meetings/config', [MeetingController::class, 'config']);
Route::get('/meetings/availability', [MeetingController::class, 'availability']);
Route::post('/meetings/book', [MeetingController::class, 'book']);

// ---- Blog "Follow" subscription (public) ----
Route::post('/subscribe', [SubscriberController::class, 'store']);

// Client page-visit beacon — logs only when a valid client token is present (checked in the controller).
Route::post('/track/visit', [TrackController::class, 'visit'])->middleware('throttle:120,1');

// ---- Search analytics (public) ----
Route::post('/search-log', [SearchController::class, 'store']);

// ---- Public invoice pay page data (token-guarded, no auth) — consumed by the frontend pay page ----
Route::get('/invoice/pay/{token}', [InvoicePayController::class, 'apiShow']);
// The pay link is the authorisation; the controller refuses a paid invoice or one that already has an address.
Route::post('/invoice/pay/{token}/billing-address', [InvoicePayController::class, 'storeBillingAddress']);

// ---- WhatsApp Business Cloud API webhook (public — Meta verifies via token/signature) ----
Route::get('/whatsapp/webhook', [WhatsappWebhookController::class, 'verify']);
Route::post('/whatsapp/webhook', [WhatsappWebhookController::class, 'receive']);
// ---- Baileys gateway → Laravel (QR/WhatsApp Web driver; auth via shared secret header) ----
Route::post('/whatsapp/gateway', [WhatsappGatewayController::class, 'handle']);

// ---- Payment webhooks (public, no auth) ----
Route::post('/webhooks/stripe', [WebhookController::class, 'stripe']);
// ---- Branding for the public site. Read on every page load, so it is deliberately small and
//      answers from the same cache the panel uses. ----
Route::get('/branding', function () {
    $b = BrandSetting::current();

    return response()->json([
        'product' => $b->productName(),
        'tagline' => $b->taglineText(),
        'logo' => $b->logoUrl(),
        'icon' => $b->iconUrl(),
        'primary' => $b->primaryColour(),
        'company_name' => $b->companyName(),
        'support_email' => $b->supportEmail(),
        'phone' => $b->phoneNumber(),
        'website_url' => $b->websiteUrl(),
        'address' => $b->addressText(),
        'header' => [
            'cta_label' => $b->headerCtaLabel(),
            'cta_url' => $b->headerCtaUrl(),
        ],
        'footer' => [
            'about' => $b->footerAbout(),
            'note' => $b->footerNote(),
        ],
        'login' => [
            'heading' => $b->loginHeading(),
            'subheading' => $b->loginSubheading(),
        ],
        'social' => $b->socialLinks(),
    ]);
});

// ---- Public product sales link: the page payload and the buy action. Token-guarded, no login,
//      same footing as the invoice pay-link above. ----
Route::get('/p/{token}', [ProductLinkController::class, 'payload']);
Route::post('/p/{token}/order', [ProductLinkController::class, 'order']);

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
    Route::get('/support/tickets', [SupportTicketController::class, 'index']);
    Route::post('/support/tickets', [SupportTicketController::class, 'store']);
    Route::get('/support/tickets/{ticket}', [SupportTicketController::class, 'show'])->whereNumber('ticket');
    Route::post('/support/tickets/{ticket}/replies', [SupportTicketController::class, 'reply'])->whereNumber('ticket');

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
    Route::get('/account/messages', [AccountMessageController::class, 'index']);
    Route::post('/account/messages', [AccountMessageController::class, 'store']);

    // Profile management
    Route::put('/account/profile', [AccountController::class, 'updateProfile']);
    Route::put('/account/password', [AccountController::class, 'updatePassword']);
    Route::post('/account/avatar', [AccountController::class, 'updateAvatar']);
    Route::post('/account/email/verify', [AccountController::class, 'sendEmailVerification']);
    Route::delete('/account', [AccountController::class, 'destroy']);

    // Saved billing addresses — the dashboard manages them, checkout picks one.
    Route::get('/account/billing-addresses', [BillingAddressController::class, 'index']);
    Route::post('/account/billing-addresses', [BillingAddressController::class, 'store']);
    Route::put('/account/billing-addresses/{billingAddress}', [BillingAddressController::class, 'update']);
    Route::delete('/account/billing-addresses/{billingAddress}', [BillingAddressController::class, 'destroy']);
    Route::post('/account/billing-addresses/{billingAddress}/default', [BillingAddressController::class, 'setDefault']);

    Route::get('/account/orders', [AccountController::class, 'orders']);
    Route::get('/account/orders/{orderNumber}', [AccountController::class, 'order']);
    Route::get('/account/invoices', [AccountController::class, 'invoices']);
    Route::get('/account/invoices/{invoice}/download', [AccountController::class, 'downloadInvoice'])->name('account.invoice.download');
    Route::get('/account/licenses/{license}/download', [AccountController::class, 'downloadLicense'])->name('account.license.download');

    // gated source download — temporary signed URL + auth + ownership check
    Route::get('/account/products/{product}/source', [AccountController::class, 'downloadSource'])
        ->middleware('signed')->name('account.source.download');
});

// ---- Bounce / complaint reports from the sending provider. Public by necessity, so it is
//      guarded by a shared secret (EMAIL_WEBHOOK_SECRET) rather than the session. ----
Route::post('/email/webhook', EmailWebhookController::class)->name('email.webhook');
