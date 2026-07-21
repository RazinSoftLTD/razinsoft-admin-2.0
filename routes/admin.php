<?php

use App\Http\Controllers\Admin\ArticleCategoryController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\AnalyticsController;
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
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\TaskController;
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
    Route::middleware(['staff', 'log.activity'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // My Profile — self-service for any panel user (no permission gate).
        Route::get('my-profile', [\App\Http\Controllers\Admin\MyProfileController::class, 'edit'])->name('my-profile.edit');
        Route::post('my-profile', [\App\Http\Controllers\Admin\MyProfileController::class, 'update'])->name('my-profile.update');

        // ===== Messenger › WhatsApp inbox =====
        Route::middleware('permission:whatsapp.view')->group(function () {
            $wa = \App\Http\Controllers\Admin\WhatsappController::class;
            Route::get('whatsapp', [$wa, 'index'])->name('whatsapp.index');
            Route::get('whatsapp/chats', [$wa, 'chats'])->name('whatsapp.chats');
            Route::get('whatsapp/unread-count', [$wa, 'unreadCount'])->name('whatsapp.unread-count');
            Route::post('whatsapp/accounts/{account}/resync', [$wa, 'resyncAccount'])->whereNumber('account')->name('whatsapp.account.resync');
            Route::post('whatsapp/number-order', [$wa, 'saveNumberOrder'])->name('whatsapp.number-order');
            Route::post('whatsapp/new-chat', [$wa, 'startChat'])->middleware('permission:whatsapp.reply')->name('whatsapp.new-chat');
            Route::get('whatsapp/chats/{chat}/members', [$wa, 'groupMembers'])->whereNumber('chat')->name('whatsapp.members');
            Route::get('whatsapp/chats/{chat}', [$wa, 'show'])->whereNumber('chat')->name('whatsapp.show');
            Route::get('whatsapp/chats/{chat}/older', [$wa, 'olderMessages'])->whereNumber('chat')->name('whatsapp.older');
            Route::post('whatsapp/chats/{chat}/send', [$wa, 'send'])->whereNumber('chat')->middleware('permission:whatsapp.reply')->name('whatsapp.send');
            Route::post('whatsapp/chats/{chat}/media', [$wa, 'sendMediaMessage'])->whereNumber('chat')->middleware('permission:whatsapp.reply')->name('whatsapp.media');
            Route::post('whatsapp/chats/{chat}/messages/{message}/edit', [$wa, 'editMessage'])->whereNumber('chat')->whereNumber('message')->middleware('permission:whatsapp.reply')->name('whatsapp.msg.edit');
            Route::post('whatsapp/chats/{chat}/messages/{message}/react', [$wa, 'reactMessage'])->whereNumber('chat')->whereNumber('message')->middleware('permission:whatsapp.reply')->name('whatsapp.msg.react');
            Route::delete('whatsapp/chats/{chat}/messages/{message}', [$wa, 'deleteMessage'])->whereNumber('chat')->whereNumber('message')->middleware('permission:whatsapp.reply')->name('whatsapp.msg.delete');
            Route::post('whatsapp/chats/{chat}/assign', [$wa, 'assign'])->whereNumber('chat')->middleware('permission:whatsapp.assign')->name('whatsapp.assign');
            Route::post('whatsapp/chats/{chat}/status', [$wa, 'status'])->whereNumber('chat')->name('whatsapp.status');
            Route::post('whatsapp/chats/{chat}/unread', [$wa, 'markUnread'])->whereNumber('chat')->name('whatsapp.unread');
            Route::post('whatsapp/chats/{chat}/label', [$wa, 'toggleLabel'])->whereNumber('chat')->name('whatsapp.label');
            Route::post('whatsapp/chats/{chat}/note', [$wa, 'addNote'])->whereNumber('chat')->name('whatsapp.note');
            Route::post('whatsapp/chats/{chat}/details', [$wa, 'updateDetails'])->whereNumber('chat')->name('whatsapp.details');
            Route::post('whatsapp/chats/{chat}/convert-lead', [$wa, 'convertToLead'])->whereNumber('chat')->middleware('permission:leads.create')->name('whatsapp.convert-lead');
            Route::post('whatsapp/chats/{chat}/avatar', [$wa, 'updateAvatar'])->whereNumber('chat')->name('whatsapp.avatar');
        });
        Route::middleware('permission:whatsapp.activity')->group(function () {
            $wact = \App\Http\Controllers\Admin\WhatsappActivityController::class;
            Route::get('whatsapp-activity', [$wact, 'index'])->name('whatsapp-activity');
            Route::get('whatsapp-activity/{account}', [$wact, 'show'])->whereNumber('account')->name('whatsapp-activity.show');
            Route::get('whatsapp-activity/{account}/chats/{chat}', [$wact, 'thread'])->whereNumber('account')->whereNumber('chat')->name('whatsapp-activity.thread');
        });
        // Each WhatsApp Config section is gated by its own permission.
        Route::middleware('permission:whatsapp.settings')->group(function () {
            $ws = \App\Http\Controllers\Admin\WhatsappSettingController::class;
            Route::get('whatsapp-settings', [$ws, 'index'])->name('whatsapp-settings');   // open the Config page
        });
        // A stray GET to the numbers collection (typed URL / old bookmark) → the Config page, not a 405.
        Route::get('whatsapp-accounts', fn () => redirect()->route('admin.whatsapp-settings'))->name('whatsapp-accounts.index');
        // Connection Method (gateway / API credentials)
        Route::middleware('permission:whatsapp.connection')->group(function () {
            $ws = \App\Http\Controllers\Admin\WhatsappSettingController::class;
            Route::post('whatsapp-settings', [$ws, 'update'])->name('whatsapp-settings.update');
            Route::post('whatsapp-settings/test', [$ws, 'test'])->name('whatsapp-settings.test');
        });
        // WhatsApp Numbers (accounts + per-number QR connection)
        Route::middleware('permission:whatsapp.numbers')->group(function () {
            $ws = \App\Http\Controllers\Admin\WhatsappSettingController::class;
            Route::post('whatsapp-accounts', [$ws, 'accountStore'])->name('whatsapp-accounts.store');
            Route::post('whatsapp-accounts/{account}', [$ws, 'accountUpdate'])->whereNumber('account')->name('whatsapp-accounts.update');
            Route::delete('whatsapp-accounts/{account}', [$ws, 'accountDestroy'])->whereNumber('account')->name('whatsapp-accounts.destroy');
            Route::post('whatsapp-accounts/{account}/restore', [$ws, 'accountRestore'])->whereNumber('account')->withTrashed()->name('whatsapp-accounts.restore');
            Route::delete('whatsapp-accounts/{account}/force', [$ws, 'accountForceDelete'])->whereNumber('account')->withTrashed()->name('whatsapp-accounts.force-delete');
            Route::get('whatsapp-connection/{account}', [$ws, 'connection'])->whereNumber('account')->name('whatsapp-connection');
            Route::get('whatsapp-connection/{account}/status', [$ws, 'connectionStatus'])->whereNumber('account')->name('whatsapp-connection.status');
            Route::post('whatsapp-connection/{account}/connect', [$ws, 'connect'])->whereNumber('account')->name('whatsapp-connection.connect');
            Route::post('whatsapp-connection/{account}/logout', [$ws, 'logout'])->whereNumber('account')->name('whatsapp-connection.logout');
        });
        // Labels
        Route::middleware('permission:whatsapp.labels')->group(function () {
            $ws = \App\Http\Controllers\Admin\WhatsappSettingController::class;
            Route::post('whatsapp-settings/labels', [$ws, 'labelStore'])->name('whatsapp-settings.labels.store');
            Route::delete('whatsapp-settings/labels/{label}', [$ws, 'labelDestroy'])->whereNumber('label')->name('whatsapp-settings.labels.destroy');
        });
        // Quick replies — add/update/delete gated by their own role permission.
        Route::middleware('permission:whatsapp.quick_replies')->group(function () {
            $wsq = \App\Http\Controllers\Admin\WhatsappSettingController::class;
            Route::post('whatsapp-settings/quick-replies', [$wsq, 'quickStore'])->name('whatsapp-settings.quick.store');
            Route::put('whatsapp-settings/quick-replies/{quickReply}', [$wsq, 'quickUpdate'])->whereNumber('quickReply')->name('whatsapp-settings.quick.update');
            Route::delete('whatsapp-settings/quick-replies/{quickReply}', [$wsq, 'quickDestroy'])->whereNumber('quickReply')->name('whatsapp-settings.quick.destroy');
        });

        // ===== Team Chat — open to every panel user; group creation is gated =====
        Route::get('chat', [\App\Http\Controllers\Admin\ChatController::class, 'index'])->name('chat.index');
        Route::get('chat/new-group', [\App\Http\Controllers\Admin\ChatController::class, 'createGroup'])
            ->middleware('permission:chat.create_group')->name('chat.groups.create');
        Route::post('chat/groups', [\App\Http\Controllers\Admin\ChatController::class, 'storeGroup'])
            ->middleware('permission:chat.create_group')->name('chat.groups.store');
        Route::post('chat/heartbeat', [\App\Http\Controllers\Admin\ChatController::class, 'heartbeat'])->name('chat.heartbeat');
        Route::post('chat/offline', [\App\Http\Controllers\Admin\ChatController::class, 'offline'])->name('chat.offline');
        Route::patch('chat/messages/{message}', [\App\Http\Controllers\Admin\ChatController::class, 'editMessage'])->whereNumber('message')->name('chat.messages.update');
        Route::post('chat/messages/{message}/forward', [\App\Http\Controllers\Admin\ChatController::class, 'forwardMessage'])->whereNumber('message')->name('chat.messages.forward');
        Route::post('chat/messages/{message}/react', [\App\Http\Controllers\Admin\ChatController::class, 'reactMessage'])->whereNumber('message')->name('chat.messages.react');
        Route::post('chat/messages/{message}/checklist', [\App\Http\Controllers\Admin\ChatController::class, 'toggleChecklist'])->whereNumber('message')->name('chat.messages.checklist');
        Route::delete('chat/messages/{message}', [\App\Http\Controllers\Admin\ChatController::class, 'destroyMessage'])->whereNumber('message')->name('chat.messages.destroy');
        Route::get('chat/{conversation}/settings', [\App\Http\Controllers\Admin\ChatController::class, 'editGroup'])->whereNumber('conversation')->name('chat.groups.edit');
        Route::post('chat/{conversation}/settings', [\App\Http\Controllers\Admin\ChatController::class, 'updateGroup'])->whereNumber('conversation')->name('chat.groups.update');
        Route::get('chat/with/{user}', [\App\Http\Controllers\Admin\ChatController::class, 'direct'])->whereNumber('user')->name('chat.direct');
        Route::get('chat/{conversation}', [\App\Http\Controllers\Admin\ChatController::class, 'show'])->whereNumber('conversation')->name('chat.show');
        Route::post('chat/{conversation}/messages', [\App\Http\Controllers\Admin\ChatController::class, 'sendMessage'])->whereNumber('conversation')->name('chat.messages.store');
        Route::get('chat/{conversation}/older', [\App\Http\Controllers\Admin\ChatController::class, 'olderMessages'])->whereNumber('conversation')->name('chat.older');
        Route::post('chat/{conversation}/typing', [\App\Http\Controllers\Admin\ChatController::class, 'typing'])->whereNumber('conversation')->name('chat.typing');
        Route::post('chat/{conversation}/read', [\App\Http\Controllers\Admin\ChatController::class, 'read'])->whereNumber('conversation')->name('chat.read');

        // ===== Book a Meeting =====
        Route::middleware('permission:meetings.settings')->group(function () {
            Route::get('meetings/settings', [\App\Http\Controllers\Admin\MeetingController::class, 'settings'])->name('meetings.settings');
            Route::post('meetings/settings', [\App\Http\Controllers\Admin\MeetingController::class, 'updateSettings'])->name('meetings.settings.update');
        });
        Route::middleware('permission:meetings.view')->group(function () {
            Route::get('meetings', [\App\Http\Controllers\Admin\MeetingController::class, 'index'])->name('meetings.index');
            Route::get('meetings/{meeting}', [\App\Http\Controllers\Admin\MeetingController::class, 'show'])->whereNumber('meeting')->name('meetings.show');
        });
        Route::middleware('permission:meetings.edit')->group(function () {
            Route::patch('meetings/{meeting}', [\App\Http\Controllers\Admin\MeetingController::class, 'update'])->whereNumber('meeting')->name('meetings.update');
            Route::patch('meetings/{meeting}/quick', [\App\Http\Controllers\Admin\MeetingController::class, 'quickUpdate'])->whereNumber('meeting')->name('meetings.quick');
            Route::get('meetings/{meeting}/edit', [\App\Http\Controllers\Admin\MeetingController::class, 'edit'])->whereNumber('meeting')->name('meetings.edit');
            Route::patch('meetings/{meeting}/reschedule', [\App\Http\Controllers\Admin\MeetingController::class, 'reschedule'])->whereNumber('meeting')->name('meetings.reschedule');
        });
        Route::delete('meetings/{meeting}', [\App\Http\Controllers\Admin\MeetingController::class, 'destroy'])->whereNumber('meeting')->middleware('permission:meetings.delete')->name('meetings.destroy');

        // ===== CRM Analytics (reports · follow-ups · by country) =====
        Route::middleware('permission:analytics.view')->group(function () {
            Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        });

        // ===== Leads =====
        Route::middleware('permission:leads.view')->group(function () {
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
            Route::post('leads/{lead}/convert-deal', [LeadController::class, 'convertDeal'])->name('leads.convert-deal');
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
            Route::post('deals/{deal}/follow-up', [DealController::class, 'followUp'])->name('deals.follow-up');
            Route::post('deals/{deal}/follow-up/{followUp}/complete', [DealController::class, 'followUpComplete'])->name('deals.follow-up.complete');
            Route::delete('deals/{deal}/follow-up/{followUp}', [DealController::class, 'followUpDestroy'])->name('deals.follow-up.destroy');
            Route::put('deals/{deal}/description', [DealController::class, 'description'])->name('deals.description');
            Route::post('deals/{deal}/attachments', [DealController::class, 'attachmentStore'])->name('deals.attachments.store');
            Route::delete('deals/{deal}/attachments/{attachment}', [DealController::class, 'attachmentDestroy'])->name('deals.attachments.destroy');
            Route::post('deals/{deal}/activity', [DealController::class, 'activity'])->name('deals.activity');
            Route::post('deals/{deal}/invoice', [DealController::class, 'invoice'])->name('deals.invoice');
        });
        Route::middleware('permission:deals.delete')->group(function () {
            Route::delete('deals/{deal}', [DealController::class, 'destroy'])->whereNumber('deal')->name('deals.destroy');
        });

        // ===== Workspace : Projects & Tasks (desk-style, rebuilt) =====
        Route::middleware('permission:projects.view')->group(function () {
            Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
            Route::get('projects/{project}/drawer', [ProjectController::class, 'drawer'])->whereNumber('project')->name('projects.drawer');
            Route::post('projects/{project}/favorite', [ProjectController::class, 'toggleFavorite'])->whereNumber('project')->name('projects.favorite');
            Route::get('projects/{project}', [ProjectController::class, 'show'])->whereNumber('project')->name('projects.show');
            Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
            Route::get('tasks/{task}', [TaskController::class, 'show'])->whereNumber('task')->name('tasks.show');
            Route::get('tasks/{task}/files/{file}/download', [TaskController::class, 'fileDownload'])->whereNumber(['task', 'file'])->name('tasks.files.download');
            Route::get('projects/{project}/files/{file}/download', [ProjectController::class, 'fileDownload'])->whereNumber(['project', 'file'])->name('projects.files.download');
            Route::get('projects/{project}/prd/{item}/download', [ProjectController::class, 'prdDownload'])->whereNumber(['project', 'item'])->name('projects.prd.download');
        });
        Route::middleware('permission:projects.create')->group(function () {
            Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
            Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
        });
        Route::middleware('permission:projects.edit')->group(function () {
            Route::post('projects/reorder', [ProjectController::class, 'reorder'])->name('projects.reorder');
            Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->whereNumber('project')->name('projects.edit');
            Route::put('projects/{project}', [ProjectController::class, 'update'])->whereNumber('project')->name('projects.update');
            Route::post('projects/{project}/status', [ProjectController::class, 'status'])->whereNumber('project')->name('projects.status');
        });

        // ---- Project sections, each with its own permission ----
        Route::middleware('permission:projects.members')->group(function () {
            Route::post('projects/{project}/members', [ProjectController::class, 'memberStore'])->whereNumber('project')->name('projects.members.store');
            Route::put('projects/{project}/members/{member}/access', [ProjectController::class, 'memberAccess'])->whereNumber(['project', 'member'])->name('projects.members.access');
            Route::delete('projects/{project}/members/{member}', [ProjectController::class, 'memberDestroy'])->whereNumber(['project', 'member'])->name('projects.members.destroy');
        });
        Route::middleware('permission:projects.settings')->group(function () {
            Route::put('projects/{project}/settings', [ProjectController::class, 'updateSettings'])->whereNumber('project')->name('projects.settings.update');
            Route::post('projects/{project}/profile', [ProjectController::class, 'updateProfile'])->whereNumber('project')->name('projects.profile.update');
        });
        Route::middleware('permission:projects.milestones')->group(function () {
            Route::post('projects/{project}/milestones', [ProjectController::class, 'milestoneStore'])->whereNumber('project')->name('projects.milestones.store');
            Route::put('projects/{project}/milestones/{milestone}', [ProjectController::class, 'milestoneUpdate'])->whereNumber(['project', 'milestone'])->name('projects.milestones.update');
            Route::delete('projects/{project}/milestones/{milestone}', [ProjectController::class, 'milestoneDestroy'])->whereNumber(['project', 'milestone'])->name('projects.milestones.destroy');
        });
        Route::middleware('permission:projects.files')->group(function () {
            Route::post('projects/{project}/files', [ProjectController::class, 'fileStore'])->whereNumber('project')->name('projects.files.store');
            Route::delete('projects/{project}/files/{file}', [ProjectController::class, 'fileDestroy'])->whereNumber(['project', 'file'])->name('projects.files.destroy');
        });
        Route::middleware('permission:projects.prd')->group(function () {
            Route::post('projects/{project}/prd', [ProjectController::class, 'prdStore'])->whereNumber('project')->name('projects.prd.store');
            Route::post('projects/{project}/prd/share', [ProjectController::class, 'prdShare'])->whereNumber('project')->name('projects.prd.share');
            Route::put('projects/{project}/prd/{item}/review', [ProjectController::class, 'prdReview'])->whereNumber(['project', 'item'])->name('projects.prd.review');
            Route::delete('projects/{project}/prd/{item}', [ProjectController::class, 'prdDestroy'])->whereNumber(['project', 'item'])->name('projects.prd.destroy');
        });
        Route::middleware('permission:projects.columns')->group(function () {
            Route::post('projects/{project}/columns', [ProjectController::class, 'columnStore'])->whereNumber('project')->name('projects.columns.store');
            Route::put('projects/{project}/columns/{column}', [ProjectController::class, 'columnUpdate'])->whereNumber(['project', 'column'])->name('projects.columns.update');
            Route::delete('projects/{project}/columns/{column}', [ProjectController::class, 'columnDestroy'])->whereNumber(['project', 'column'])->name('projects.columns.destroy');
        });

        // ---- Tasks, section by section ----
        Route::middleware('permission:tasks.create')->group(function () {
            Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
        });
        Route::middleware('permission:tasks.edit')->group(function () {
            Route::put('tasks/{task}', [TaskController::class, 'update'])->whereNumber('task')->name('tasks.update');
        });
        Route::middleware('permission:tasks.status')->group(function () {
            Route::post('tasks/{task}/status', [TaskController::class, 'status'])->whereNumber('task')->name('tasks.status');
        });
        Route::middleware('permission:tasks.comments')->group(function () {
            Route::post('tasks/{task}/comments', [TaskController::class, 'commentStore'])->whereNumber('task')->name('tasks.comments.store');
            Route::delete('tasks/{task}/comments/{comment}', [TaskController::class, 'commentDestroy'])->whereNumber(['task', 'comment'])->name('tasks.comments.destroy');
        });
        Route::middleware('permission:tasks.attachments')->group(function () {
            Route::post('tasks/{task}/files', [TaskController::class, 'fileStore'])->whereNumber('task')->name('tasks.files.store');
            Route::delete('tasks/{task}/files/{file}', [TaskController::class, 'fileDestroy'])->whereNumber(['task', 'file'])->name('tasks.files.destroy');
        });
        Route::middleware('permission:tasks.time')->group(function () {
            Route::post('projects/{project}/time', [ProjectController::class, 'timeStore'])->whereNumber('project')->name('projects.time.store');
            Route::delete('projects/{project}/time/{log}', [ProjectController::class, 'timeDestroy'])->whereNumber(['project', 'log'])->name('projects.time.destroy');
            Route::post('tasks/{task}/timer/start', [TaskController::class, 'timerStart'])->whereNumber('task')->name('tasks.timer.start');
            Route::post('tasks/{task}/timer/pause', [TaskController::class, 'timerPause'])->whereNumber('task')->name('tasks.timer.pause');
            Route::post('tasks/{task}/timer/stop', [TaskController::class, 'timerStop'])->whereNumber('task')->name('tasks.timer.stop');
            Route::post('tasks/{task}/timer/cancel', [TaskController::class, 'timerCancel'])->whereNumber('task')->name('tasks.timer.cancel');
        });

        Route::middleware('permission:projects.delete')->group(function () {
            Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->whereNumber('project')->name('projects.destroy');
        });
        Route::middleware('permission:tasks.delete')->group(function () {
            Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->whereNumber('task')->name('tasks.destroy');
        });

        // ===== Settings : Project Config (categories + default board columns) =====
        Route::middleware('permission:projects.settings')->group(function () {
            Route::get('project-config', [\App\Http\Controllers\Admin\ProjectConfigController::class, 'index'])->name('project-config');
            Route::post('project-config/categories', [\App\Http\Controllers\Admin\ProjectConfigController::class, 'categoryStore'])->name('project-config.categories.store');
            Route::put('project-config/categories/{category}', [\App\Http\Controllers\Admin\ProjectConfigController::class, 'categoryUpdate'])->whereNumber('category')->name('project-config.categories.update');
            Route::delete('project-config/categories/{category}', [\App\Http\Controllers\Admin\ProjectConfigController::class, 'categoryDestroy'])->whereNumber('category')->name('project-config.categories.destroy');
            Route::post('project-config/columns', [\App\Http\Controllers\Admin\ProjectConfigController::class, 'columnStore'])->name('project-config.columns.store');
            Route::put('project-config/columns/{column}', [\App\Http\Controllers\Admin\ProjectConfigController::class, 'columnUpdate'])->whereNumber('column')->name('project-config.columns.update');
            Route::delete('project-config/columns/{column}', [\App\Http\Controllers\Admin\ProjectConfigController::class, 'columnDestroy'])->whereNumber('column')->name('project-config.columns.destroy');
        });

        // ===== Clients =====
        Route::middleware('permission:clients.view')->group(function () {
            Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        });
        // Activity logs — each page has its own permission (see Roles → Activity Logs).
        Route::middleware('permission:activity.client')->group(function () {
            Route::get('client-activity', [\App\Http\Controllers\Admin\ClientActivityLogController::class, 'index'])->name('client-activity');
            Route::get('client-activity/details', [\App\Http\Controllers\Admin\ClientActivityLogController::class, 'details'])->name('client-activity.details');
            Route::get('client-activity/errors', [\App\Http\Controllers\Admin\ClientActivityLogController::class, 'errors'])->name('client-activity.errors');
        });
        // Blogs / Products share one route; the exact permission (activity.blogs / activity.products)
        // is checked in the controller since it depends on {type}.
        Route::get('client-activity/{type}', [\App\Http\Controllers\Admin\ClientActivityLogController::class, 'content'])->whereIn('type', ['blogs', 'products'])->name('client-activity.content');
        Route::middleware('permission:clients.view')->group(function () {
            Route::get('clients/{client}', [ClientController::class, 'show'])->whereNumber('client')->name('clients.show');
        });
        Route::middleware('permission:clients.create')->group(function () {
            Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
            Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
            Route::post('clients/quick', [ClientController::class, 'quickStore'])->name('clients.quick');
        });
        // Import / Export — its own permission (export runs on the index route, gated in the controller).
        Route::middleware('permission:clients.import_export')->group(function () {
            Route::get('clients/import/sample', [ClientController::class, 'importSample'])->name('clients.import.sample');
            Route::get('clients/import', [ClientController::class, 'importForm'])->name('clients.import.form');
            Route::post('clients/import', [ClientController::class, 'import'])->name('clients.import');
            Route::post('clients/import/undo', [ClientController::class, 'undoImport'])->name('clients.import.undo');
        });
        Route::middleware('permission:clients.edit')->group(function () {
            Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->whereNumber('client')->name('clients.edit');
            Route::put('clients/{client}', [ClientController::class, 'update'])->whereNumber('client')->name('clients.update');
            Route::patch('clients/{client}/status', [ClientController::class, 'updateStatus'])->whereNumber('client')->name('clients.status');
            Route::post('clients/{client}/documents', [ClientController::class, 'storeDocument'])->whereNumber('client')->name('clients.documents.store');
            Route::delete('clients/{client}/documents/{document}', [ClientController::class, 'destroyDocument'])->whereNumber('client')->whereNumber('document')->name('clients.documents.destroy');
        });
        Route::middleware('permission:clients.delete')->group(function () {
            Route::delete('clients-bulk', [ClientController::class, 'bulkDestroy'])->name('clients.bulk-destroy');
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
        });
        // CRM settings — configurable lead sources & departments.
        Route::middleware('permission:leads.settings')->group(function () {
            Route::get('crm-settings', [\App\Http\Controllers\Admin\CrmSettingController::class, 'index'])->name('crm-settings');
            Route::post('crm-settings/options', [\App\Http\Controllers\Admin\CrmSettingController::class, 'storeOption'])->name('crm-settings.options.store');
            Route::patch('crm-settings/options/{option}', [\App\Http\Controllers\Admin\CrmSettingController::class, 'updateOption'])->whereNumber('option')->name('crm-settings.options.update');
            Route::delete('crm-settings/options/{option}', [\App\Http\Controllers\Admin\CrmSettingController::class, 'destroyOption'])->whereNumber('option')->name('crm-settings.options.destroy');
            Route::post('crm-settings/client-labels', [\App\Http\Controllers\Admin\CrmSettingController::class, 'storeClientLabel'])->name('crm-settings.client-labels.store');
            Route::patch('crm-settings/client-labels/{clientLabel}', [\App\Http\Controllers\Admin\CrmSettingController::class, 'updateClientLabel'])->whereNumber('clientLabel')->name('crm-settings.client-labels.update');
            Route::delete('crm-settings/client-labels/{clientLabel}', [\App\Http\Controllers\Admin\CrmSettingController::class, 'destroyClientLabel'])->whereNumber('clientLabel')->name('crm-settings.client-labels.destroy');
        });

        // Ticket settings (agents, types, reply templates) — separate, admin/manager-level gate.
        Route::middleware('permission:tickets.settings')->group(function () {
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
        });
        // ===== Installation Plans (own permission set) =====
        $ip = \App\Http\Controllers\Admin\InstallationPlanController::class;
        Route::middleware('permission:installation_plans.view')->group(function () use ($ip) {
            Route::get('installation-plans', [$ip, 'index'])->name('installation-plans');
        });
        Route::prefix('installation-plans')->name('installation-plans.')->group(function () use ($ip) {
            Route::middleware('permission:installation_plans.create')->group(function () use ($ip) {
                Route::post('products', [$ip, 'productStore'])->name('products.store');
                Route::post('{product}/features', [$ip, 'featureStore'])->whereNumber('product')->name('features.store');
                Route::post('{product}/plans', [$ip, 'planStore'])->whereNumber('product')->name('plans.store');
            });
            Route::middleware('permission:installation_plans.view')->group(function () use ($ip) {
                Route::get('{product}/preview', [$ip, 'preview'])->whereNumber('product')->name('preview');
            });
            Route::middleware('permission:installation_plans.edit')->group(function () use ($ip) {
                Route::post('{product}/status', [$ip, 'status'])->whereNumber('product')->name('status');
                Route::put('{product}/features/{feature}', [$ip, 'featureUpdate'])->whereNumber(['product', 'feature'])->name('features.update');
                Route::put('{product}/plans/{plan}', [$ip, 'planUpdate'])->whereNumber(['product', 'plan'])->name('plans.update');
                Route::post('{product}/plans/{plan}/toggle', [$ip, 'toggle'])->whereNumber(['product', 'plan'])->name('toggle');
            });
            Route::middleware('permission:installation_plans.delete')->group(function () use ($ip) {
                Route::delete('{product}/features/{feature}', [$ip, 'featureDestroy'])->whereNumber(['product', 'feature'])->name('features.destroy');
                Route::delete('{product}/plans/{plan}', [$ip, 'planDestroy'])->whereNumber(['product', 'plan'])->name('plans.destroy');
            });
            Route::middleware('permission:installation_plans.copy')->group(function () use ($ip) {
                Route::post('{product}/copy-from', [$ip, 'copyFrom'])->whereNumber('product')->name('copy-from');
            });
        });
        Route::middleware('permission:products.create')->group(function () {
            Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
        });
        Route::middleware('permission:products.clone')->group(function () {
            Route::post('products/{product}/clone', [ProductController::class, 'clone'])->name('products.clone');
        });
        Route::middleware('permission:products.publish')->group(function () {
            Route::post('products/{product}/publish', [ProductController::class, 'togglePublish'])->name('products.publish');
        });
        Route::middleware('permission:products.edit')->group(function () {
            Route::get('products/{product}/edit', [ProductController::class, 'edit'])->whereNumber('product')->name('products.edit');
            Route::put('products/{product}', [ProductController::class, 'update'])->whereNumber('product')->name('products.update');
        });
        // Gallery, features, FAQs … everything on the product's "manage" screens.
        Route::middleware('permission:products.relations')->group(function () {
            Route::get('products/{product}/manage/{relation}', [ProductRelationController::class, 'edit'])->whereNumber('product')->name('products.relation.edit');
            Route::post('products/{product}/gallery-images/{image}/move', [ProductRelationController::class, 'moveGalleryImage'])->name('products.gallery.move');
            Route::post('products/{product}/{relation}', [ProductRelationController::class, 'store'])->name('products.relation.store');
            Route::put('products/{product}/{relation}/{id}', [ProductRelationController::class, 'update'])->name('products.relation.update');
            Route::post('products/{product}/{relation}/{id}/toggle', [ProductRelationController::class, 'toggle'])->name('products.relation.toggle');
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
            Route::post('invoices/{invoice}/pay-options', [ClientInvoiceController::class, 'payOptions'])->whereNumber('invoice')->name('invoices.pay-options');
            Route::post('invoices/{invoice}/shipping-address', [ClientInvoiceController::class, 'shippingAddress'])->whereNumber('invoice')->name('invoices.shipping-address');
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
        // Granular invoice operations (each its own permission — see Roles & Permissions).
        Route::middleware('permission:invoices.send')->group(function () {
            Route::post('invoices/{invoice}/send', [ClientInvoiceController::class, 'send'])->whereNumber('invoice')->name('invoices.send');
            Route::post('invoices/{invoice}/reminder', [ClientInvoiceController::class, 'reminder'])->whereNumber('invoice')->name('invoices.reminder');
        });
        Route::middleware('permission:invoices.cancel')->group(function () {
            Route::post('invoices/{invoice}/cancel', [ClientInvoiceController::class, 'cancel'])->whereNumber('invoice')->name('invoices.cancel');
        });
        Route::middleware('permission:invoices.duplicate')->group(function () {
            Route::post('invoices/{invoice}/duplicate', [ClientInvoiceController::class, 'duplicate'])->whereNumber('invoice')->name('invoices.duplicate');
        });
        // Bin — recoverable deleted invoices.
        Route::middleware('permission:invoices.bin')->group(function () {
            Route::get('invoices-bin', [ClientInvoiceController::class, 'bin'])->name('invoices.bin');
            Route::post('invoices-bin/{id}/restore', [ClientInvoiceController::class, 'restore'])->whereNumber('id')->name('invoices.bin.restore');
            Route::delete('invoices-bin/{id}', [ClientInvoiceController::class, 'forceDelete'])->whereNumber('id')->name('invoices.bin.force-delete');
        });
        // Invoice Configuration — units, taxes/charges, branding logo.
        Route::middleware('permission:invoices.configure')->group(function () {
            $ic = \App\Http\Controllers\Admin\InvoiceConfigController::class;
            Route::get('invoice-config', [$ic, 'index'])->name('invoice-config');
            Route::post('invoice-config/branding', [$ic, 'updateBranding'])->name('invoice-config.branding');
            Route::post('invoice-config/units', [$ic, 'storeUnit'])->name('invoice-config.units.store');
            Route::patch('invoice-config/units/{unit}', [$ic, 'updateUnit'])->whereNumber('unit')->name('invoice-config.units.update');
            Route::delete('invoice-config/units/{unit}', [$ic, 'destroyUnit'])->whereNumber('unit')->name('invoice-config.units.destroy');
            Route::post('invoice-config/taxes', [$ic, 'storeTax'])->name('invoice-config.taxes.store');
            Route::patch('invoice-config/taxes/{tax}', [$ic, 'updateTax'])->whereNumber('tax')->name('invoice-config.taxes.update');
            Route::delete('invoice-config/taxes/{tax}', [$ic, 'destroyTax'])->whereNumber('tax')->name('invoice-config.taxes.destroy');
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

        // ===== Contact Us (website messages) =====
        Route::middleware('permission:messages.view')->group(function () {
            Route::get('messages', [ContactMessageController::class, 'index'])->name('messages.index');
            Route::get('messages/{message}', [ContactMessageController::class, 'show'])->whereNumber('message')->name('messages.show');
        });
        Route::middleware('permission:messages.edit')->group(fn () => Route::patch('messages/{message}/status', [ContactMessageController::class, 'updateStatus'])->whereNumber('message')->name('messages.status'));
        Route::middleware('permission:messages.delete')->group(fn () => Route::delete('messages/{message}', [ContactMessageController::class, 'destroy'])->whereNumber('message')->name('messages.destroy'));

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

    // ---- Careers openings (draft → publish workflow behind the public careers page) ----
    Route::middleware('permission:careers.view')->group(function () {
        Route::get('jobs', [\App\Http\Controllers\Admin\JobController::class, 'index'])->name('jobs.index');
    });
    Route::middleware('permission:careers.create')->group(function () {
        Route::get('jobs/create', [\App\Http\Controllers\Admin\JobController::class, 'create'])->name('jobs.create');
        Route::post('jobs', [\App\Http\Controllers\Admin\JobController::class, 'store'])->name('jobs.store');
    });
    Route::middleware('permission:careers.edit')->group(function () {
        Route::get('jobs/{job}/edit', [\App\Http\Controllers\Admin\JobController::class, 'edit'])->whereNumber('job')->name('jobs.edit');
        Route::put('jobs/{job}', [\App\Http\Controllers\Admin\JobController::class, 'update'])->whereNumber('job')->name('jobs.update');
    });
    Route::middleware('permission:careers.publish')->group(function () {
        Route::post('jobs/{job}/publish', [\App\Http\Controllers\Admin\JobController::class, 'togglePublish'])->whereNumber('job')->name('jobs.publish');
    });
    Route::middleware('permission:careers.delete')->group(function () {
        Route::delete('jobs/{job}', [\App\Http\Controllers\Admin\JobController::class, 'destroy'])->whereNumber('job')->name('jobs.destroy');
    });

    // ---- Super admin only (role=admin): per-user permission overrides, roles, admin users ----
    Route::middleware('admin')->group(function () {
        Route::patch('staff/{staff}/role', [StaffController::class, 'updateRole'])->whereNumber('staff')->name('staff.role');
        Route::get('staff/{staff}/permissions', [StaffController::class, 'permissions'])->whereNumber('staff')->name('staff.permissions');
        Route::put('staff/{staff}/permissions', [StaffController::class, 'updatePermissions'])->whereNumber('staff')->name('staff.permissions.update');
        Route::post('roles/{role}/duplicate', [RoleController::class, 'duplicate'])->whereNumber('role')->name('roles.duplicate');
        Route::resource('roles', RoleController::class)->except('show');
        Route::resource('users', UserController::class)->except('show');

        // Email / SMTP settings + templates
        // Activity → Employee (every employee's actions).
        Route::middleware('permission:activity.employee')->group(function () {
            Route::get('activity-logs', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('activity-logs');
            Route::get('activity-logs/{employee}', [\App\Http\Controllers\Admin\ActivityLogController::class, 'show'])->whereNumber('employee')->name('activity-logs.show');
        });

        // ---- Activity → CodeCanyon (official Envato API only; no scraping) ----
        Route::middleware('permission:codecanyon.view')->group(function () {
            $cc = \App\Http\Controllers\Admin\CodeCanyonController::class;
            Route::get('codecanyon', [$cc, 'index'])->name('codecanyon.index');
            Route::get('codecanyon/authors/{author}', [$cc, 'author'])->whereNumber('author')->name('codecanyon.author');
            Route::get('codecanyon/products/{product}', [$cc, 'product'])->whereNumber('product')->name('codecanyon.product');
        });
        Route::middleware('permission:codecanyon.manage')->group(function () {
            $cc = \App\Http\Controllers\Admin\CodeCanyonController::class;
            Route::post('codecanyon/authors', [$cc, 'storeAuthor'])->name('codecanyon.authors.store');
            Route::delete('codecanyon/authors/{author}', [$cc, 'destroyAuthor'])->whereNumber('author')->name('codecanyon.authors.destroy');
            Route::post('codecanyon/products', [$cc, 'storeProduct'])->name('codecanyon.products.store');
            Route::put('codecanyon/products/{product}', [$cc, 'updateProduct'])->whereNumber('product')->name('codecanyon.products.update');
            Route::delete('codecanyon/products/{product}', [$cc, 'destroyProduct'])->whereNumber('product')->name('codecanyon.products.destroy');
            Route::post('codecanyon/niches', [$cc, 'storeNiche'])->name('codecanyon.niches.store');
            Route::delete('codecanyon/niches/{niche}', [$cc, 'destroyNiche'])->whereNumber('niche')->name('codecanyon.niches.destroy');
            Route::post('codecanyon/sync', [$cc, 'sync'])->name('codecanyon.sync');
        });
        Route::middleware('permission:codecanyon.settings')->group(function () {
            $cc = \App\Http\Controllers\Admin\CodeCanyonController::class;
            Route::get('codecanyon-settings', [$cc, 'settings'])->name('codecanyon-settings');
            Route::put('codecanyon-settings', [$cc, 'saveSettings'])->name('codecanyon-settings.save');
        });

        // Settings → Bin (recoverable clients + invoices; super admin only, enforced in the controller).
        Route::get('bin', [\App\Http\Controllers\Admin\BinController::class, 'index'])->name('bin');
        Route::post('bin/clients/{id}/restore', [\App\Http\Controllers\Admin\BinController::class, 'restoreClient'])->whereNumber('id')->name('bin.clients.restore');
        Route::post('bin/projects/{id}/restore', [\App\Http\Controllers\Admin\BinController::class, 'restoreProject'])->whereNumber('id')->name('bin.projects.restore');
        Route::delete('bin/projects/{id}', [\App\Http\Controllers\Admin\BinController::class, 'forceDeleteProject'])->whereNumber('id')->name('bin.projects.force-delete');
        Route::delete('bin/projects/empty', [\App\Http\Controllers\Admin\BinController::class, 'emptyProjects'])->name('bin.projects.empty');
        Route::delete('bin/clients/{id}', [\App\Http\Controllers\Admin\BinController::class, 'forceDeleteClient'])->whereNumber('id')->name('bin.clients.force-delete');
        Route::post('bin/clients/restore', [\App\Http\Controllers\Admin\BinController::class, 'bulkRestoreClients'])->name('bin.clients.bulk-restore');
        Route::delete('bin/clients', [\App\Http\Controllers\Admin\BinController::class, 'bulkForceDeleteClients'])->name('bin.clients.bulk-delete');
        Route::post('bin/invoices/restore', [\App\Http\Controllers\Admin\BinController::class, 'bulkRestoreInvoices'])->name('bin.invoices.bulk-restore');
        Route::delete('bin/invoices', [\App\Http\Controllers\Admin\BinController::class, 'bulkForceDeleteInvoices'])->name('bin.invoices.bulk-delete');
        // Empty the Trash — permanently delete everything in a tab.
        Route::delete('bin/clients/empty', [\App\Http\Controllers\Admin\BinController::class, 'emptyClients'])->name('bin.clients.empty');
        Route::delete('bin/invoices/empty', [\App\Http\Controllers\Admin\BinController::class, 'emptyInvoices'])->name('bin.invoices.empty');
        Route::delete('bin/whatsapp/empty', [\App\Http\Controllers\Admin\BinController::class, 'emptyWhatsapp'])->name('bin.whatsapp.empty');

        Route::get('email-settings', [\App\Http\Controllers\Admin\EmailSettingController::class, 'index'])->name('email-settings');
        Route::post('email-settings', [\App\Http\Controllers\Admin\EmailSettingController::class, 'update'])->name('email-settings.update');
        Route::post('email-settings/test', [\App\Http\Controllers\Admin\EmailSettingController::class, 'sendTest'])->name('email-settings.test');
        Route::get('email-settings/templates/{template}', [\App\Http\Controllers\Admin\EmailSettingController::class, 'editTemplate'])->whereNumber('template')->name('email-settings.templates.edit');
        Route::put('email-settings/templates/{template}', [\App\Http\Controllers\Admin\EmailSettingController::class, 'updateTemplate'])->whereNumber('template')->name('email-settings.templates.update');
    });

    // ---- HR (permission-gated: super admin can grant these to employee roles) ----
    Route::middleware('staff')->group(function () {
        Route::middleware('permission:employees.view')->group(function () {
            Route::post('staff-designations', [StaffController::class, 'storeDesignation'])->name('staff.designations.store');
            Route::post('staff-departments', [StaffController::class, 'storeDepartment'])->name('staff.departments.store');
            Route::resource('staff', StaffController::class)->except('show');
            Route::get('staff/{staff}', [StaffController::class, 'show'])->whereNumber('staff')->name('staff.show');
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
