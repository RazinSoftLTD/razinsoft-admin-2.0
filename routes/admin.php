<?php

use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductRelationController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    // ---- Auth ----
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'attempt'])->name('login.attempt');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    // ---- Panel: admin + staff. Each section is gated by a permission; admins hold all. ----
    Route::middleware('staff')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // CRM — leads
        Route::middleware('permission:leads')->group(function () {
            Route::get('leads/follow-up', [\App\Http\Controllers\Admin\LeadController::class, 'followUp'])->name('leads.follow-up');
            Route::get('leads/import', [\App\Http\Controllers\Admin\LeadController::class, 'importForm'])->name('leads.import.form');
            Route::get('leads/import/sample', [\App\Http\Controllers\Admin\LeadController::class, 'importSample'])->name('leads.import.sample');
            Route::post('leads/import', [\App\Http\Controllers\Admin\LeadController::class, 'import'])->name('leads.import');
            Route::resource('leads', \App\Http\Controllers\Admin\LeadController::class);
            Route::post('leads/{lead}/convert', [\App\Http\Controllers\Admin\LeadController::class, 'convert'])->name('leads.convert');
            Route::post('leads/{lead}/mark-contacted', [\App\Http\Controllers\Admin\LeadController::class, 'markContacted'])->name('leads.mark-contacted');
            Route::post('leads/{lead}/snooze', [\App\Http\Controllers\Admin\LeadController::class, 'snooze'])->name('leads.snooze');
            Route::post('leads/{lead}/status', [\App\Http\Controllers\Admin\LeadController::class, 'status'])->name('leads.status');
            Route::post('leads/{lead}/follow-up-date', [\App\Http\Controllers\Admin\LeadController::class, 'scheduleFollowUp'])->name('leads.schedule-follow-up');
        });

        // CRM — deals
        Route::middleware('permission:deals')->group(function () {
            Route::resource('deals', \App\Http\Controllers\Admin\DealController::class);
            Route::post('deals/{deal}/stage', [\App\Http\Controllers\Admin\DealController::class, 'stage'])->name('deals.stage');
            Route::post('deals/{deal}/invoice', [\App\Http\Controllers\Admin\DealController::class, 'invoice'])->name('deals.invoice');
        });

        // Clients
        Route::middleware('permission:clients')->group(function () {
            Route::resource('clients', \App\Http\Controllers\Admin\ClientController::class)->except('show');
            Route::get('clients/{client}', [\App\Http\Controllers\Admin\ClientController::class, 'show'])->name('clients.show')->whereNumber('client');
        });

        // Products
        Route::middleware('permission:products')->group(function () {
            Route::resource('products', ProductController::class);
            Route::post('products/{product}/publish', [ProductController::class, 'togglePublish'])->name('products.publish');
            Route::post('products/{product}/clone', [ProductController::class, 'clone'])->name('products.clone');
            Route::post('products/{product}/gallery-images/{image}/move', [ProductRelationController::class, 'moveGalleryImage'])->name('products.gallery.move');
            Route::get('products/{product}/manage/{relation}', [ProductRelationController::class, 'edit'])->name('products.relation.edit');
            Route::post('products/{product}/{relation}', [ProductRelationController::class, 'store'])->name('products.relation.store');
            Route::put('products/{product}/{relation}/{id}', [ProductRelationController::class, 'update'])->name('products.relation.update');
            Route::delete('products/{product}/{relation}/{id}', [ProductRelationController::class, 'destroy'])->name('products.relation.destroy');
        });

        // Orders
        Route::middleware('permission:orders')->group(function () {
            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
            Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
            Route::get('orders/{order}/invoice', [OrderController::class, 'downloadInvoice'])->name('orders.invoice.download');
            Route::get('orders/{order}/licenses/{license}', [OrderController::class, 'downloadLicense'])->name('orders.license.download');
            Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        });

        // Coupons
        Route::middleware('permission:coupons')->group(function () {
            Route::resource('coupons', CouponController::class)->except('show');
        });

        // Billing — invoices, recurring, templates, currencies
        Route::middleware('permission:invoices')->group(function () {
            Route::resource('invoices', \App\Http\Controllers\Admin\ClientInvoiceController::class);
            Route::get('invoices/{invoice}/pdf', [\App\Http\Controllers\Admin\ClientInvoiceController::class, 'pdf'])->name('invoices.pdf');
            Route::post('invoices/{invoice}/payments', [\App\Http\Controllers\Admin\InvoicePaymentController::class, 'store'])->name('invoices.payments.store');
            Route::delete('invoices/{invoice}/payments/{payment}', [\App\Http\Controllers\Admin\InvoicePaymentController::class, 'destroy'])->name('invoices.payments.destroy');
            Route::post('invoices/{invoice}/request-payment', [\App\Http\Controllers\Admin\ClientInvoiceController::class, 'requestPayment'])->name('invoices.request-payment');
            Route::post('invoices/{invoice}/send', [\App\Http\Controllers\Admin\ClientInvoiceController::class, 'send'])->name('invoices.send');

            Route::resource('recurring', \App\Http\Controllers\Admin\RecurringInvoiceController::class)->except('show')->parameters(['recurring' => 'recurring']);
            Route::post('recurring/{recurring}/run', [\App\Http\Controllers\Admin\RecurringInvoiceController::class, 'run'])->name('recurring.run');

            Route::resource('invoice-templates', \App\Http\Controllers\Admin\InvoiceTemplateController::class)->except('show');

            Route::get('currencies', [\App\Http\Controllers\Admin\CurrencyController::class, 'index'])->name('currencies.index');
            Route::post('currencies', [\App\Http\Controllers\Admin\CurrencyController::class, 'store'])->name('currencies.store');
            Route::put('currencies/{currency}', [\App\Http\Controllers\Admin\CurrencyController::class, 'update'])->name('currencies.update');
            Route::delete('currencies/{currency}', [\App\Http\Controllers\Admin\CurrencyController::class, 'destroy'])->name('currencies.destroy');
        });

        // Questions
        Route::middleware('permission:questions')->group(function () {
            Route::get('questions', [QuestionController::class, 'index'])->name('questions.index');
            Route::post('questions/{question}/answer', [QuestionController::class, 'reply'])->name('questions.reply');
            Route::delete('questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');
        });

        // Reviews
        Route::middleware('permission:reviews')->group(function () {
            Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
            Route::put('reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
            Route::post('reviews/{review}/toggle', [ReviewController::class, 'toggle'])->name('reviews.toggle');
            Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
        });

        // Messages
        Route::middleware('permission:messages')->group(function () {
            Route::get('messages', [\App\Http\Controllers\Admin\ContactMessageController::class, 'index'])->name('messages.index');
            Route::delete('messages/{message}', [\App\Http\Controllers\Admin\ContactMessageController::class, 'destroy'])->name('messages.destroy');
        });

        // Subscribers (blog "Follow" list)
        Route::middleware('permission:subscribers')->group(function () {
            Route::get('subscribers', [\App\Http\Controllers\Admin\SubscriberController::class, 'index'])->name('subscribers.index');
            Route::post('subscribers', [\App\Http\Controllers\Admin\SubscriberController::class, 'store'])->name('subscribers.store');
            Route::put('subscribers/{subscriber}', [\App\Http\Controllers\Admin\SubscriberController::class, 'update'])->name('subscribers.update');
            Route::delete('subscribers/{subscriber}', [\App\Http\Controllers\Admin\SubscriberController::class, 'destroy'])->name('subscribers.destroy');
        });

        // Searches
        Route::middleware('permission:searches')->group(function () {
            Route::get('searches', [\App\Http\Controllers\Admin\SearchController::class, 'index'])->name('searches.index');
            Route::delete('searches', [\App\Http\Controllers\Admin\SearchController::class, 'destroy'])->name('searches.destroy');
        });

        // Blog
        Route::middleware('permission:blog')->group(function () {
            Route::resource('article-categories', \App\Http\Controllers\Admin\ArticleCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('authors', \App\Http\Controllers\Admin\AuthorController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('articles', \App\Http\Controllers\Admin\ArticleController::class)->except('show');
            Route::post('articles/{article}/publish', [\App\Http\Controllers\Admin\ArticleController::class, 'togglePublish'])->name('articles.publish');
            Route::post('article-image', [\App\Http\Controllers\Admin\ArticleController::class, 'uploadImage'])->name('articles.upload-image');
        });
    });

    // ---- Super admin only (role=admin): manage staff, their permissions, and admin users ----
    Route::middleware('admin')->group(function () {
        Route::resource('staff', \App\Http\Controllers\Admin\StaffController::class)->except('show');
        Route::resource('users', UserController::class)->except('show');
    });
});
