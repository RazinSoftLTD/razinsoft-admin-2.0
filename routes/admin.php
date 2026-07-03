<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductRelationController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    // ---- Auth ----
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'attempt'])->name('login.attempt');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    // ---- Panel access: admin + staff ----
    Route::middleware('staff')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // CRM — leads (staff see only their own; admin sees all — enforced in the controller).
        Route::resource('leads', \App\Http\Controllers\Admin\LeadController::class);
        Route::post('leads/{lead}/convert', [\App\Http\Controllers\Admin\LeadController::class, 'convert'])->name('leads.convert');
    });

    // ---- Admin-only ----
    Route::middleware('admin')->group(function () {
        // Staff / employees management
        Route::resource('staff', \App\Http\Controllers\Admin\StaffController::class)->except('show');

        Route::resource('products', ProductController::class); // index/create/store/show/edit/update/destroy
        Route::post('products/{product}/publish', [ProductController::class, 'togglePublish'])->name('products.publish');

        // Per-section management page (its own page per tab) + relation CRUD
        Route::get('products/{product}/manage/{relation}', [ProductRelationController::class, 'edit'])->name('products.relation.edit');
        Route::post('products/{product}/{relation}', [ProductRelationController::class, 'store'])->name('products.relation.store');
        Route::put('products/{product}/{relation}/{id}', [ProductRelationController::class, 'update'])->name('products.relation.update');
        Route::delete('products/{product}/{relation}/{id}', [ProductRelationController::class, 'destroy'])->name('products.relation.destroy');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');

        Route::get('questions', [QuestionController::class, 'index'])->name('questions.index');
        Route::post('questions/{question}/answer', [QuestionController::class, 'reply'])->name('questions.reply');
        Route::delete('questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');

        Route::resource('article-categories', \App\Http\Controllers\Admin\ArticleCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('authors', \App\Http\Controllers\Admin\AuthorController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('articles', \App\Http\Controllers\Admin\ArticleController::class)->except('show');
        Route::post('articles/{article}/publish', [\App\Http\Controllers\Admin\ArticleController::class, 'togglePublish'])->name('articles.publish');
        Route::post('article-image', [\App\Http\Controllers\Admin\ArticleController::class, 'uploadImage'])->name('articles.upload-image');

        Route::get('messages', [\App\Http\Controllers\Admin\ContactMessageController::class, 'index'])->name('messages.index');
        Route::delete('messages/{message}', [\App\Http\Controllers\Admin\ContactMessageController::class, 'destroy'])->name('messages.destroy');

        Route::get('searches', [\App\Http\Controllers\Admin\SearchController::class, 'index'])->name('searches.index');
        Route::delete('searches', [\App\Http\Controllers\Admin\SearchController::class, 'destroy'])->name('searches.destroy');

        Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
        Route::put('reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
        Route::post('reviews/{review}/toggle', [ReviewController::class, 'toggle'])->name('reviews.toggle');
        Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

        Route::resource('coupons', CouponController::class)->except('show');

        // Clients = customer users; Users kept for managing admins.
        Route::resource('clients', \App\Http\Controllers\Admin\ClientController::class)->except('show');

        // CRM billing — invoices
        Route::resource('invoices', \App\Http\Controllers\Admin\ClientInvoiceController::class);
        Route::get('invoices/{invoice}/pdf', [\App\Http\Controllers\Admin\ClientInvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/payments', [\App\Http\Controllers\Admin\InvoicePaymentController::class, 'store'])->name('invoices.payments.store');
        Route::delete('invoices/{invoice}/payments/{payment}', [\App\Http\Controllers\Admin\InvoicePaymentController::class, 'destroy'])->name('invoices.payments.destroy');
        Route::resource('users', UserController::class)->except('show');
    });
});
