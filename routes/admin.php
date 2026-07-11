<?php

use App\Http\Controllers\Admin\ArticleCategoryController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\AuthorController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ClientInvoiceController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DealController;
use App\Http\Controllers\Admin\InvoicePaymentController;
use App\Http\Controllers\Admin\InvoiceTemplateController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductRelationController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\RecurringInvoiceController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SubscriberController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    // ---- Auth ----
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'attempt'])->name('login.attempt');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    // ---- Panel: admin + staff. Every route is gated by a `permission:module.action` key.
    //      Model-bound wildcards use whereNumber() so string paths (create/import/…) never clash. ----
    Route::middleware('staff')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // My Profile — self-service for any panel user (no permission gate).
        Route::get('my-profile', [\App\Http\Controllers\Admin\MyProfileController::class, 'edit'])->name('my-profile.edit');
        Route::post('my-profile', [\App\Http\Controllers\Admin\MyProfileController::class, 'update'])->name('my-profile.update');

        // ===== Team Chat — open to every panel user; group creation is gated =====
        Route::get('chat', [\App\Http\Controllers\Admin\ChatController::class, 'index'])->name('chat.index');
        Route::get('chat/new-group', [\App\Http\Controllers\Admin\ChatController::class, 'createGroup'])
            ->middleware('permission:chat.create_group')->name('chat.groups.create');
        Route::post('chat/groups', [\App\Http\Controllers\Admin\ChatController::class, 'storeGroup'])
            ->middleware('permission:chat.create_group')->name('chat.groups.store');
        Route::post('chat/heartbeat', [\App\Http\Controllers\Admin\ChatController::class, 'heartbeat'])->name('chat.heartbeat');
        Route::post('chat/offline', [\App\Http\Controllers\Admin\ChatController::class, 'offline'])->name('chat.offline');
        Route::delete('chat/messages/{message}', [\App\Http\Controllers\Admin\ChatController::class, 'destroyMessage'])->whereNumber('message')->name('chat.messages.destroy');
        Route::get('chat/{conversation}/settings', [\App\Http\Controllers\Admin\ChatController::class, 'editGroup'])->whereNumber('conversation')->name('chat.groups.edit');
        Route::post('chat/{conversation}/settings', [\App\Http\Controllers\Admin\ChatController::class, 'updateGroup'])->whereNumber('conversation')->name('chat.groups.update');
        Route::get('chat/with/{user}', [\App\Http\Controllers\Admin\ChatController::class, 'direct'])->whereNumber('user')->name('chat.direct');
        Route::get('chat/{conversation}', [\App\Http\Controllers\Admin\ChatController::class, 'show'])->whereNumber('conversation')->name('chat.show');
        Route::post('chat/{conversation}/messages', [\App\Http\Controllers\Admin\ChatController::class, 'sendMessage'])->whereNumber('conversation')->name('chat.messages.store');
        Route::post('chat/{conversation}/typing', [\App\Http\Controllers\Admin\ChatController::class, 'typing'])->whereNumber('conversation')->name('chat.typing');
        Route::post('chat/{conversation}/read', [\App\Http\Controllers\Admin\ChatController::class, 'read'])->whereNumber('conversation')->name('chat.read');

        // ===== Leads =====
        Route::middleware('permission:leads.view')->group(function () {
            Route::get('leads/follow-up', [LeadController::class, 'followUp'])->name('leads.follow-up');
            Route::get('leads/import/sample', [LeadController::class, 'importSample'])->name('leads.import.sample');
            Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
            Route::get('leads/{lead}', [LeadController::class, 'show'])->whereNumber('lead')->name('leads.show');
        });
        Route::middleware('permission:leads.create')->group(function () {
            Route::get('leads/create', [LeadController::class, 'create'])->name('leads.create');
            Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
            Route::get('leads/import', [LeadController::class, 'importForm'])->name('leads.import.form');
            Route::post('leads/import', [LeadController::class, 'import'])->name('leads.import');
        });
        Route::middleware('permission:leads.edit')->group(function () {
            Route::get('leads/{lead}/edit', [LeadController::class, 'edit'])->whereNumber('lead')->name('leads.edit');
            Route::put('leads/{lead}', [LeadController::class, 'update'])->whereNumber('lead')->name('leads.update');
            Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
            Route::post('leads/{lead}/mark-contacted', [LeadController::class, 'markContacted'])->name('leads.mark-contacted');
            Route::post('leads/{lead}/snooze', [LeadController::class, 'snooze'])->name('leads.snooze');
            Route::post('leads/{lead}/status', [LeadController::class, 'status'])->name('leads.status');
            Route::post('leads/{lead}/follow-up-date', [LeadController::class, 'scheduleFollowUp'])->name('leads.schedule-follow-up');
        });
        Route::middleware('permission:leads.delete')->group(function () {
            Route::delete('leads/{lead}', [LeadController::class, 'destroy'])->whereNumber('lead')->name('leads.destroy');
        });

        // ===== Deals =====
        Route::middleware('permission:deals.view')->group(function () {
            Route::get('deals', [DealController::class, 'index'])->name('deals.index');
            Route::get('deals/{deal}', [DealController::class, 'show'])->whereNumber('deal')->name('deals.show');
        });
        Route::middleware('permission:deals.create')->group(function () {
            Route::get('deals/create', [DealController::class, 'create'])->name('deals.create');
            Route::post('deals', [DealController::class, 'store'])->name('deals.store');
        });
        Route::middleware('permission:deals.edit')->group(function () {
            Route::get('deals/{deal}/edit', [DealController::class, 'edit'])->whereNumber('deal')->name('deals.edit');
            Route::put('deals/{deal}', [DealController::class, 'update'])->whereNumber('deal')->name('deals.update');
            Route::post('deals/{deal}/stage', [DealController::class, 'stage'])->name('deals.stage');
            Route::post('deals/{deal}/invoice', [DealController::class, 'invoice'])->name('deals.invoice');
        });
        Route::middleware('permission:deals.delete')->group(function () {
            Route::delete('deals/{deal}', [DealController::class, 'destroy'])->whereNumber('deal')->name('deals.destroy');
        });

        // ===== Clients =====
        Route::middleware('permission:clients.view')->group(function () {
            Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
            Route::get('clients/export', [ClientController::class, 'export'])->name('clients.export');
            Route::get('clients/{client}', [ClientController::class, 'show'])->whereNumber('client')->name('clients.show');
        });
        Route::middleware('permission:clients.create')->group(function () {
            Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
            Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
            Route::post('clients/quick', [ClientController::class, 'quickStore'])->name('clients.quick');
            Route::post('clients/import', [ClientController::class, 'import'])->name('clients.import');
        });
        Route::middleware('permission:clients.edit')->group(function () {
            Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->whereNumber('client')->name('clients.edit');
            Route::put('clients/{client}', [ClientController::class, 'update'])->whereNumber('client')->name('clients.update');
            Route::patch('clients/{client}/status', [ClientController::class, 'updateStatus'])->whereNumber('client')->name('clients.status');
            Route::post('clients/{client}/documents', [ClientController::class, 'storeDocument'])->whereNumber('client')->name('clients.documents.store');
            Route::delete('clients/{client}/documents/{document}', [ClientController::class, 'destroyDocument'])->whereNumber('client')->whereNumber('document')->name('clients.documents.destroy');
        });
        Route::middleware('permission:clients.delete')->group(function () {
            Route::delete('clients/{client}', [ClientController::class, 'destroy'])->whereNumber('client')->name('clients.destroy');
        });

        // ===== Support tickets =====
        Route::middleware('permission:tickets.view')->group(function () {
            Route::get('tickets', [\App\Http\Controllers\Admin\TicketController::class, 'index'])->name('tickets.index');
            Route::get('tickets/export', [\App\Http\Controllers\Admin\TicketController::class, 'export'])->name('tickets.export');
            Route::get('tickets/{ticket}', [\App\Http\Controllers\Admin\TicketController::class, 'show'])->whereNumber('ticket')->name('tickets.show');
        });
        Route::middleware('permission:tickets.create')->group(function () {
            Route::get('tickets/create', [\App\Http\Controllers\Admin\TicketController::class, 'create'])->name('tickets.create');
            Route::post('tickets', [\App\Http\Controllers\Admin\TicketController::class, 'store'])->name('tickets.store');
            Route::post('ticket-groups', [\App\Http\Controllers\Admin\TicketController::class, 'storeGroup'])->name('tickets.groups.store');
            Route::post('ticket-types', [\App\Http\Controllers\Admin\TicketController::class, 'storeType'])->name('tickets.types.store');
        });
        Route::post('tickets/{ticket}/replies', [\App\Http\Controllers\Admin\TicketController::class, 'reply'])->whereNumber('ticket')->middleware('permission:tickets.reply')->name('tickets.reply');
        Route::middleware('permission:tickets.edit')->group(function () {
            Route::patch('tickets/{ticket}/status', [\App\Http\Controllers\Admin\TicketController::class, 'updateStatus'])->whereNumber('ticket')->name('tickets.status');
            Route::patch('tickets/{ticket}/assign', [\App\Http\Controllers\Admin\TicketController::class, 'assign'])->whereNumber('ticket')->name('tickets.assign');
            // Ticket settings (agents, types, reply templates)
            Route::get('ticket-settings', [\App\Http\Controllers\Admin\TicketSettingController::class, 'index'])->name('tickets.settings');
            Route::post('ticket-settings/agents', [\App\Http\Controllers\Admin\TicketSettingController::class, 'storeAgent'])->name('tickets.settings.agents.store');
            Route::patch('ticket-settings/agents/{agent}', [\App\Http\Controllers\Admin\TicketSettingController::class, 'updateAgent'])->name('tickets.settings.agents.update');
            Route::delete('ticket-settings/agents/{agent}', [\App\Http\Controllers\Admin\TicketSettingController::class, 'destroyAgent'])->name('tickets.settings.agents.destroy');
            Route::post('ticket-settings/types', [\App\Http\Controllers\Admin\TicketSettingController::class, 'storeType'])->name('tickets.settings.types.store');
            Route::delete('ticket-settings/types/{type}', [\App\Http\Controllers\Admin\TicketSettingController::class, 'destroyType'])->name('tickets.settings.types.destroy');
            Route::post('ticket-settings/templates', [\App\Http\Controllers\Admin\TicketSettingController::class, 'storeTemplate'])->name('tickets.settings.templates.store');
            Route::patch('ticket-settings/templates/{template}', [\App\Http\Controllers\Admin\TicketSettingController::class, 'updateTemplate'])->name('tickets.settings.templates.update');
            Route::delete('ticket-settings/templates/{template}', [\App\Http\Controllers\Admin\TicketSettingController::class, 'destroyTemplate'])->name('tickets.settings.templates.destroy');
        });
        Route::delete('tickets/{ticket}', [\App\Http\Controllers\Admin\TicketController::class, 'destroy'])->whereNumber('ticket')->name('tickets.destroy');

        // ===== Products =====
        Route::middleware('permission:products.view')->group(function () {
            Route::get('products', [ProductController::class, 'index'])->name('products.index');
            Route::get('products/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');
            Route::get('products/{product}/manage/{relation}', [ProductRelationController::class, 'edit'])->whereNumber('product')->name('products.relation.edit');
        });
        Route::middleware('permission:products.create')->group(function () {
            Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
            Route::post('products/{product}/clone', [ProductController::class, 'clone'])->name('products.clone');
        });
        Route::middleware('permission:products.edit')->group(function () {
            Route::get('products/{product}/edit', [ProductController::class, 'edit'])->whereNumber('product')->name('products.edit');
            Route::put('products/{product}', [ProductController::class, 'update'])->whereNumber('product')->name('products.update');
            Route::post('products/{product}/publish', [ProductController::class, 'togglePublish'])->name('products.publish');
            Route::post('products/{product}/gallery-images/{image}/move', [ProductRelationController::class, 'moveGalleryImage'])->name('products.gallery.move');
            Route::post('products/{product}/{relation}', [ProductRelationController::class, 'store'])->name('products.relation.store');
            Route::put('products/{product}/{relation}/{id}', [ProductRelationController::class, 'update'])->name('products.relation.update');
            Route::delete('products/{product}/{relation}/{id}', [ProductRelationController::class, 'destroy'])->name('products.relation.destroy');
        });
        Route::middleware('permission:products.delete')->group(function () {
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->whereNumber('product')->name('products.destroy');
        });

        // ===== Orders =====
        Route::middleware('permission:orders.view')->group(function () {
            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}/invoice', [OrderController::class, 'downloadInvoice'])->whereNumber('order')->name('orders.invoice.download');
            Route::get('orders/{order}/licenses/{license}', [OrderController::class, 'downloadLicense'])->whereNumber('order')->name('orders.license.download');
            Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('orders.show');
        });
        Route::middleware('permission:orders.create')->group(function () {
            Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
            Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        });

        // ===== Coupons =====
        Route::middleware('permission:coupons.view')->group(fn () => Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index'));
        Route::middleware('permission:coupons.create')->group(function () {
            Route::get('coupons/create', [CouponController::class, 'create'])->name('coupons.create');
            Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');
        });
        Route::middleware('permission:coupons.edit')->group(function () {
            Route::get('coupons/{coupon}/edit', [CouponController::class, 'edit'])->whereNumber('coupon')->name('coupons.edit');
            Route::put('coupons/{coupon}', [CouponController::class, 'update'])->whereNumber('coupon')->name('coupons.update');
        });
        Route::middleware('permission:coupons.delete')->group(fn () => Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->whereNumber('coupon')->name('coupons.destroy'));

        // ===== Invoices (+ recurring / templates / currencies) =====
        Route::middleware('permission:invoices.view')->group(function () {
            Route::get('invoices', [ClientInvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/{invoice}', [ClientInvoiceController::class, 'show'])->whereNumber('invoice')->name('invoices.show');
            Route::get('invoices/{invoice}/pdf', [ClientInvoiceController::class, 'pdf'])->whereNumber('invoice')->name('invoices.pdf');
            Route::get('recurring', [RecurringInvoiceController::class, 'index'])->name('recurring.index');
            Route::get('invoice-templates', [InvoiceTemplateController::class, 'index'])->name('invoice-templates.index');
            Route::get('currencies', [CurrencyController::class, 'index'])->name('currencies.index');
        });
        Route::middleware('permission:invoices.create')->group(function () {
            Route::get('invoices/create', [ClientInvoiceController::class, 'create'])->name('invoices.create');
            Route::post('invoices', [ClientInvoiceController::class, 'store'])->name('invoices.store');
            Route::get('recurring/create', [RecurringInvoiceController::class, 'create'])->name('recurring.create');
            Route::post('recurring', [RecurringInvoiceController::class, 'store'])->name('recurring.store');
            Route::get('invoice-templates/create', [InvoiceTemplateController::class, 'create'])->name('invoice-templates.create');
            Route::post('invoice-templates', [InvoiceTemplateController::class, 'store'])->name('invoice-templates.store');
            Route::post('currencies', [CurrencyController::class, 'store'])->name('currencies.store');
        });
        Route::middleware('permission:invoices.edit')->group(function () {
            Route::get('invoices/{invoice}/edit', [ClientInvoiceController::class, 'edit'])->whereNumber('invoice')->name('invoices.edit');
            Route::put('invoices/{invoice}', [ClientInvoiceController::class, 'update'])->whereNumber('invoice')->name('invoices.update');
            Route::post('invoices/{invoice}/request-payment', [ClientInvoiceController::class, 'requestPayment'])->name('invoices.request-payment');
            Route::post('invoices/{invoice}/send', [ClientInvoiceController::class, 'send'])->name('invoices.send');
            Route::get('recurring/{recurring}/edit', [RecurringInvoiceController::class, 'edit'])->whereNumber('recurring')->name('recurring.edit');
            Route::put('recurring/{recurring}', [RecurringInvoiceController::class, 'update'])->whereNumber('recurring')->name('recurring.update');
            Route::post('recurring/{recurring}/run', [RecurringInvoiceController::class, 'run'])->name('recurring.run');
            Route::get('invoice-templates/{invoice_template}/edit', [InvoiceTemplateController::class, 'edit'])->whereNumber('invoice_template')->name('invoice-templates.edit');
            Route::put('invoice-templates/{invoice_template}', [InvoiceTemplateController::class, 'update'])->whereNumber('invoice_template')->name('invoice-templates.update');
            Route::put('currencies/{currency}', [CurrencyController::class, 'update'])->whereNumber('currency')->name('currencies.update');
        });
        Route::middleware('permission:invoices.delete')->group(function () {
            Route::delete('invoices/{invoice}', [ClientInvoiceController::class, 'destroy'])->whereNumber('invoice')->name('invoices.destroy');
            Route::delete('recurring/{recurring}', [RecurringInvoiceController::class, 'destroy'])->whereNumber('recurring')->name('recurring.destroy');
            Route::delete('invoice-templates/{invoice_template}', [InvoiceTemplateController::class, 'destroy'])->whereNumber('invoice_template')->name('invoice-templates.destroy');
            Route::delete('currencies/{currency}', [CurrencyController::class, 'destroy'])->whereNumber('currency')->name('currencies.destroy');
        });
        Route::middleware('permission:invoices.finance')->group(function () {
            Route::post('invoices/{invoice}/payments', [InvoicePaymentController::class, 'store'])->name('invoices.payments.store');
            Route::delete('invoices/{invoice}/payments/{payment}', [InvoicePaymentController::class, 'destroy'])->name('invoices.payments.destroy');
        });

        // ===== Questions =====
        Route::middleware('permission:questions.view')->group(fn () => Route::get('questions', [QuestionController::class, 'index'])->name('questions.index'));
        Route::middleware('permission:questions.answer')->group(fn () => Route::post('questions/{question}/answer', [QuestionController::class, 'reply'])->name('questions.reply'));
        Route::middleware('permission:questions.delete')->group(fn () => Route::delete('questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy'));

        // ===== Reviews =====
        Route::middleware('permission:reviews.view')->group(fn () => Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index'));
        Route::middleware('permission:reviews.edit')->group(function () {
            Route::put('reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
            Route::post('reviews/{review}/toggle', [ReviewController::class, 'toggle'])->name('reviews.toggle');
        });
        Route::middleware('permission:reviews.delete')->group(fn () => Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy'));

        // ===== Messages =====
        Route::middleware('permission:messages.view')->group(fn () => Route::get('messages', [ContactMessageController::class, 'index'])->name('messages.index'));
        Route::middleware('permission:messages.delete')->group(fn () => Route::delete('messages/{message}', [ContactMessageController::class, 'destroy'])->name('messages.destroy'));

        // ===== Subscribers =====
        Route::middleware('permission:subscribers.view')->group(fn () => Route::get('subscribers', [SubscriberController::class, 'index'])->name('subscribers.index'));
        Route::middleware('permission:subscribers.create')->group(function () {
            Route::post('subscribers', [SubscriberController::class, 'store'])->name('subscribers.store');
            Route::put('subscribers/{subscriber}', [SubscriberController::class, 'update'])->whereNumber('subscriber')->name('subscribers.update');
        });
        Route::middleware('permission:subscribers.delete')->group(fn () => Route::delete('subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->whereNumber('subscriber')->name('subscribers.destroy'));

        // ===== Searches =====
        Route::middleware('permission:searches.view')->group(fn () => Route::get('searches', [SearchController::class, 'index'])->name('searches.index'));
        Route::middleware('permission:searches.delete')->group(fn () => Route::delete('searches', [SearchController::class, 'destroy'])->name('searches.destroy'));

        // ===== Blog =====
        Route::middleware('permission:blog.view')->group(function () {
            Route::get('article-categories', [ArticleCategoryController::class, 'index'])->name('article-categories.index');
            Route::get('authors', [AuthorController::class, 'index'])->name('authors.index');
            Route::get('articles', [ArticleController::class, 'index'])->name('articles.index');
        });
        Route::middleware('permission:blog.create')->group(function () {
            Route::post('article-categories', [ArticleCategoryController::class, 'store'])->name('article-categories.store');
            Route::post('authors', [AuthorController::class, 'store'])->name('authors.store');
            Route::get('articles/create', [ArticleController::class, 'create'])->name('articles.create');
            Route::post('articles', [ArticleController::class, 'store'])->name('articles.store');
        });
        Route::middleware('permission:blog.edit')->group(function () {
            Route::put('article-categories/{article_category}', [ArticleCategoryController::class, 'update'])->whereNumber('article_category')->name('article-categories.update');
            Route::put('authors/{author}', [AuthorController::class, 'update'])->whereNumber('author')->name('authors.update');
            Route::get('articles/{article}/edit', [ArticleController::class, 'edit'])->name('articles.edit'); // Article binds by slug (no whereNumber)
            Route::put('articles/{article}', [ArticleController::class, 'update'])->name('articles.update');
            Route::post('articles/{article}/publish', [ArticleController::class, 'togglePublish'])->name('articles.publish');
            Route::post('article-image', [ArticleController::class, 'uploadImage'])->name('articles.upload-image');
        });
        Route::middleware('permission:blog.delete')->group(function () {
            Route::delete('article-categories/{article_category}', [ArticleCategoryController::class, 'destroy'])->whereNumber('article_category')->name('article-categories.destroy');
            Route::delete('authors/{author}', [AuthorController::class, 'destroy'])->whereNumber('author')->name('authors.destroy');
            Route::delete('articles/{article}', [ArticleController::class, 'destroy'])->name('articles.destroy'); // slug-bound
        });
    });

    // ---- Super admin only (role=admin): per-user permission overrides, roles, admin users ----
    Route::middleware('admin')->group(function () {
        Route::get('staff/{staff}/permissions', [StaffController::class, 'permissions'])->whereNumber('staff')->name('staff.permissions');
        Route::put('staff/{staff}/permissions', [StaffController::class, 'updatePermissions'])->whereNumber('staff')->name('staff.permissions.update');
        Route::post('roles/{role}/duplicate', [RoleController::class, 'duplicate'])->whereNumber('role')->name('roles.duplicate');
        Route::resource('roles', RoleController::class)->except('show');
        Route::resource('users', UserController::class)->except('show');
    });

    // ---- HR (permission-gated: super admin can grant these to employee roles) ----
    Route::middleware('staff')->group(function () {
        Route::middleware('permission:employees.view')->group(function () {
            Route::patch('staff/{staff}/role', [StaffController::class, 'updateRole'])->whereNumber('staff')->name('staff.role');
            Route::post('staff-designations', [StaffController::class, 'storeDesignation'])->name('staff.designations.store');
            Route::post('staff-departments', [StaffController::class, 'storeDepartment'])->name('staff.departments.store');
            Route::resource('staff', StaffController::class)->except('show');
        });
        Route::middleware('permission:designations.view')->group(function () {
            Route::resource('designations', \App\Http\Controllers\Admin\DesignationController::class)->only(['index', 'store', 'update', 'destroy']);
        });
        Route::middleware('permission:departments.view')->group(function () {
            Route::resource('departments', \App\Http\Controllers\Admin\DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
        });

        // Leave — employees request their own; approvers review.
        Route::middleware('permission:leave.view')->group(function () {
            Route::get('leaves', [\App\Http\Controllers\Admin\LeaveController::class, 'index'])->name('leaves.index');
            Route::delete('leaves/{leave}', [\App\Http\Controllers\Admin\LeaveController::class, 'destroy'])->whereNumber('leave')->name('leaves.destroy');
            Route::patch('leaves/{leave}/status', [\App\Http\Controllers\Admin\LeaveController::class, 'updateStatus'])->whereNumber('leave')->name('leaves.status');
        });
        Route::middleware('permission:leave.create')->group(function () {
            Route::get('leaves/create', [\App\Http\Controllers\Admin\LeaveController::class, 'create'])->name('leaves.create');
            Route::post('leaves', [\App\Http\Controllers\Admin\LeaveController::class, 'store'])->name('leaves.store');
        });
    });
});
