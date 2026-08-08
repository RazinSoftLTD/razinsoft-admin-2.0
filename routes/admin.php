<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\ArticleCategoryController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\PasswordResetController;
use App\Http\Controllers\Admin\AuthorController;
use App\Http\Controllers\Admin\BinController;
use App\Http\Controllers\Admin\CartActivityController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\ClientActivityLogController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ClientInvoiceController;
use App\Http\Controllers\Admin\CodeCanyonController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CrmSettingController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DealController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\EmailAnalyticsController;
use App\Http\Controllers\Admin\EmailCampaignController;
use App\Http\Controllers\Admin\EmailConfigController;
use App\Http\Controllers\Admin\EmailLogController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\FollowUpController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\InstallationPlanController;
use App\Http\Controllers\Admin\InvoiceConfigController;
use App\Http\Controllers\Admin\InvoicePaymentController;
use App\Http\Controllers\Admin\InvoiceTemplateController;
use App\Http\Controllers\Admin\JobController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\LeadFollowUpController;
use App\Http\Controllers\Admin\LeaveController;
use App\Http\Controllers\Admin\MeetingController;
use App\Http\Controllers\Admin\MetaCapiController;
use App\Http\Controllers\Admin\MyProfileController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductRelationController;
use App\Http\Controllers\Admin\ProjectConfigController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\RazinAiController;
use App\Http\Controllers\Admin\RecurringInvoiceController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SubscriberController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\TicketSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WhatsappActivityController;
use App\Http\Controllers\Admin\WhatsappController;
use App\Http\Controllers\Admin\WhatsappLinkController;
use App\Http\Controllers\Admin\WhatsappSettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    // ---- Auth ----
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'attempt'])->name('login.attempt');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    // ---- Forgot password. Throttled: this form takes an email address and sends mail, so it is
    //      both a way to guess at accounts and a way to use us to spam someone's inbox. ----
    $pr = PasswordResetController::class;
    Route::get('forgot-password', [$pr, 'showRequest'])->name('password.request');
    Route::post('forgot-password', [$pr, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [$pr, 'showReset'])->name('password.reset');
    Route::post('reset-password', [$pr, 'reset'])->middleware('throttle:6,1')->name('password.update');

    // ---- Panel: admin + staff. Every route is gated by a `permission:module.action` key.
    //      Model-bound wildcards use whereNumber() so string paths (create/import/…) never clash. ----
    Route::middleware(['staff', 'log.activity'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // My Profile — self-service for any panel user (no permission gate).
        Route::get('my-profile', [MyProfileController::class, 'edit'])->name('my-profile.edit');
        Route::post('my-profile', [MyProfileController::class, 'update'])->name('my-profile.update');

        // Light / dark preference — a display choice, so no permission gate: everyone has eyes.
        Route::post('theme', [ThemeController::class, 'update'])->name('theme');

        // ===== Messenger › WhatsApp inbox =====
        Route::middleware('permission:whatsapp.view')->group(function () {
            $wa = WhatsappController::class;
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
            Route::get('whatsapp/chats/{chat}/templates', [$wa, 'templates'])->whereNumber('chat')->name('whatsapp.templates');
            Route::post('whatsapp/chats/{chat}/template', [$wa, 'sendTemplate'])->whereNumber('chat')->middleware('permission:whatsapp.reply')->name('whatsapp.send-template');
            Route::post('whatsapp/chats/{chat}/messages/{message}/retry', [$wa, 'retry'])->whereNumber('chat')->whereNumber('message')->middleware('permission:whatsapp.reply')->name('whatsapp.retry');
            Route::post('whatsapp/chats/{chat}/media', [$wa, 'sendMediaMessage'])->whereNumber('chat')->middleware('permission:whatsapp.reply')->name('whatsapp.media');
            Route::post('whatsapp/chats/{chat}/messages/{message}/edit', [$wa, 'editMessage'])->whereNumber('chat')->whereNumber('message')->middleware('permission:whatsapp.reply')->name('whatsapp.msg.edit');
            Route::post('whatsapp/chats/{chat}/messages/{message}/react', [$wa, 'reactMessage'])->whereNumber('chat')->whereNumber('message')->middleware('permission:whatsapp.reply')->name('whatsapp.msg.react');
            Route::delete('whatsapp/chats/{chat}/messages/{message}', [$wa, 'deleteMessage'])->whereNumber('chat')->whereNumber('message')->middleware('permission:whatsapp.reply')->name('whatsapp.msg.delete');
            Route::post('whatsapp/chats/{chat}/assign', [$wa, 'assign'])->whereNumber('chat')->middleware('permission:whatsapp.assign')->name('whatsapp.assign');
            Route::post('whatsapp/chats/{chat}/status', [$wa, 'status'])->whereNumber('chat')->name('whatsapp.status');
            Route::post('whatsapp/chats/{chat}/unread', [$wa, 'markUnread'])->whereNumber('chat')->name('whatsapp.unread');
            Route::post('whatsapp/chats/{chat}/label', [$wa, 'toggleLabel'])->whereNumber('chat')->name('whatsapp.label');
            Route::post('whatsapp/chats/{chat}/pin', [$wa, 'togglePin'])->whereNumber('chat')->name('whatsapp.pin');
            Route::post('whatsapp/chats/{chat}/block', [$wa, 'toggleBlock'])->whereNumber('chat')->name('whatsapp.block');
            Route::delete('whatsapp/chats/{chat}', [$wa, 'destroyChat'])->whereNumber('chat')->name('whatsapp.chat.destroy');
            Route::post('whatsapp/chats/{chat}/note', [$wa, 'addNote'])->whereNumber('chat')->name('whatsapp.note');
            Route::post('whatsapp/chats/{chat}/details', [$wa, 'updateDetails'])->whereNumber('chat')->name('whatsapp.details');
            Route::post('whatsapp/chats/{chat}/convert-lead', [$wa, 'convertToLead'])->whereNumber('chat')->middleware('permission:leads.create')->name('whatsapp.convert-lead');
            Route::post('whatsapp/chats/{chat}/avatar', [$wa, 'updateAvatar'])->whereNumber('chat')->name('whatsapp.avatar');
        });
        Route::middleware('permission:whatsapp.activity')->group(function () {
            $wact = WhatsappActivityController::class;
            Route::get('whatsapp-activity', [$wact, 'index'])->name('whatsapp-activity');
            Route::get('whatsapp-activity/{account}', [$wact, 'show'])->whereNumber('account')->name('whatsapp-activity.show');
            Route::get('whatsapp-activity/{account}/chats/{chat}', [$wact, 'thread'])->whereNumber('account')->whereNumber('chat')->name('whatsapp-activity.thread');
        });
        // Each WhatsApp Config section is gated by its own permission.
        Route::middleware('permission:whatsapp.settings')->group(function () {
            $ws = WhatsappSettingController::class;
            Route::get('whatsapp-settings', [$ws, 'index'])->name('whatsapp-settings');   // open the Config page
        });

        // Razin AI has its own permissions — reading the assistant's settings, changing how it
        // speaks, and editing the FAQ shelf are three different amounts of trust.
        Route::group([], function () {
            $rai = RazinAiController::class;
            Route::middleware('permission:razin_ai.view')->get('razin-ai', [$rai, 'index'])->name('razin-ai');
            Route::middleware('permission:razin_ai.edit')->post('razin-ai', [$rai, 'update'])->name('razin-ai.update');
            Route::middleware('permission:razin_ai.faqs')->group(function () use ($rai) {
                Route::post('razin-ai/faqs', [$rai, 'storeFaq'])->name('razin-ai.faqs.store');
                Route::put('razin-ai/faqs/{faq}', [$rai, 'updateFaq'])->whereNumber('faq')->name('razin-ai.faqs.update');
                Route::patch('razin-ai/faqs/{faq}', [$rai, 'toggleFaq'])->whereNumber('faq')->name('razin-ai.faqs.toggle');
                Route::delete('razin-ai/faqs/{faq}', [$rai, 'destroyFaq'])->whereNumber('faq')->name('razin-ai.faqs.destroy');
            });
        });
        // A stray GET to the numbers collection (typed URL / old bookmark) → the Config page, not a 405.
        Route::get('whatsapp-accounts', fn () => redirect()->route('admin.whatsapp-settings'))->name('whatsapp-accounts.index');
        // Connection Method (gateway / API credentials)
        Route::middleware('permission:whatsapp.connection')->group(function () {
            $ws = WhatsappSettingController::class;
            Route::post('whatsapp-settings', [$ws, 'update'])->name('whatsapp-settings.update');
            Route::post('whatsapp-settings/test', [$ws, 'test'])->name('whatsapp-settings.test');
        });
        // WhatsApp Numbers (accounts + per-number QR connection)
        Route::middleware('permission:whatsapp.numbers')->group(function () {
            $ws = WhatsappSettingController::class;
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
            $ws = WhatsappSettingController::class;
            Route::post('whatsapp-settings/labels', [$ws, 'labelStore'])->name('whatsapp-settings.labels.store');
            Route::post('whatsapp-settings/labels/order', [$ws, 'labelOrder'])->name('whatsapp-settings.labels.order');
            Route::delete('whatsapp-settings/labels/{label}', [$ws, 'labelDestroy'])->whereNumber('label')->name('whatsapp-settings.labels.destroy');
        });
        // Quick replies — add/update/delete gated by their own role permission.
        Route::middleware('permission:whatsapp.quick_replies')->group(function () {
            $wsq = WhatsappSettingController::class;
            Route::post('whatsapp-settings/quick-replies', [$wsq, 'quickStore'])->name('whatsapp-settings.quick.store');
            Route::put('whatsapp-settings/quick-replies/{quickReply}', [$wsq, 'quickUpdate'])->whereNumber('quickReply')->name('whatsapp-settings.quick.update');
            Route::delete('whatsapp-settings/quick-replies/{quickReply}', [$wsq, 'quickDestroy'])->whereNumber('quickReply')->name('whatsapp-settings.quick.destroy');
        });

        // ===== Team Chat — open to every panel user; group creation is gated =====
        Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
        Route::get('chat/new-group', [ChatController::class, 'createGroup'])
            ->middleware('permission:chat.create_group')->name('chat.groups.create');
        Route::post('chat/groups', [ChatController::class, 'storeGroup'])
            ->middleware('permission:chat.create_group')->name('chat.groups.store');
        Route::post('chat/heartbeat', [ChatController::class, 'heartbeat'])->name('chat.heartbeat');
        Route::post('chat/offline', [ChatController::class, 'offline'])->name('chat.offline');
        Route::patch('chat/messages/{message}', [ChatController::class, 'editMessage'])->whereNumber('message')->name('chat.messages.update');
        Route::post('chat/messages/{message}/forward', [ChatController::class, 'forwardMessage'])->whereNumber('message')->name('chat.messages.forward');
        Route::post('chat/messages/{message}/react', [ChatController::class, 'reactMessage'])->whereNumber('message')->name('chat.messages.react');
        Route::post('chat/messages/{message}/checklist', [ChatController::class, 'toggleChecklist'])->whereNumber('message')->name('chat.messages.checklist');
        Route::delete('chat/messages/{message}', [ChatController::class, 'destroyMessage'])->whereNumber('message')->name('chat.messages.destroy');
        Route::get('chat/{conversation}/settings', [ChatController::class, 'editGroup'])->whereNumber('conversation')->name('chat.groups.edit');
        Route::post('chat/{conversation}/settings', [ChatController::class, 'updateGroup'])->whereNumber('conversation')->name('chat.groups.update');
        Route::get('chat/with/{user}', [ChatController::class, 'direct'])->whereNumber('user')->name('chat.direct');
        Route::get('chat/{conversation}', [ChatController::class, 'show'])->whereNumber('conversation')->name('chat.show');
        Route::post('chat/{conversation}/messages', [ChatController::class, 'sendMessage'])->whereNumber('conversation')->name('chat.messages.store');
        Route::get('chat/{conversation}/older', [ChatController::class, 'olderMessages'])->whereNumber('conversation')->name('chat.older');
        Route::post('chat/{conversation}/typing', [ChatController::class, 'typing'])->whereNumber('conversation')->name('chat.typing');
        Route::post('chat/{conversation}/read', [ChatController::class, 'read'])->whereNumber('conversation')->name('chat.read');

        // ===== Book a Meeting =====
        Route::middleware('permission:meetings.settings')->group(function () {
            Route::get('meetings/settings', [MeetingController::class, 'settings'])->name('meetings.settings');
            Route::post('meetings/settings', [MeetingController::class, 'updateSettings'])->name('meetings.settings.update');
        });
        Route::middleware('permission:meetings.view')->group(function () {
            Route::get('meetings', [MeetingController::class, 'index'])->name('meetings.index');
            Route::get('meetings/{meeting}', [MeetingController::class, 'show'])->whereNumber('meeting')->name('meetings.show');
        });
        Route::middleware('permission:meetings.edit')->group(function () {
            Route::patch('meetings/{meeting}', [MeetingController::class, 'update'])->whereNumber('meeting')->name('meetings.update');
            Route::patch('meetings/{meeting}/quick', [MeetingController::class, 'quickUpdate'])->whereNumber('meeting')->name('meetings.quick');
            Route::get('meetings/{meeting}/edit', [MeetingController::class, 'edit'])->whereNumber('meeting')->name('meetings.edit');
            Route::patch('meetings/{meeting}/reschedule', [MeetingController::class, 'reschedule'])->whereNumber('meeting')->name('meetings.reschedule');
        });
        Route::delete('meetings/{meeting}', [MeetingController::class, 'destroy'])->whereNumber('meeting')->middleware('permission:meetings.delete')->name('meetings.destroy');

        // ===== CRM Analytics (reports · follow-ups · by country) =====
        Route::middleware('permission:analytics.view')->group(function () {
            Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        });

        // ===== Gallery =====
        // Every stored file in one list. Reading only — deleting from here would break whatever
        // row still points at the file, silently, and the gallery cannot know what that is.
        Route::middleware('permission:gallery.view')->group(function () {
            Route::get('gallery', [GalleryController::class, 'index'])->name('gallery.index');
            Route::get('gallery/file', [GalleryController::class, 'file'])->name('gallery.file');
            Route::post('gallery/refresh', [GalleryController::class, 'refresh'])->name('gallery.refresh');
        });

        // ===== Leads =====
        // ===== Gallery =====
        // Every stored file in one list. Reading only — deleting from here would break whatever
        // row still points at the file, silently, and the gallery cannot know what that is.
        Route::middleware('permission:gallery.view')->group(function () {
            Route::get('gallery', [GalleryController::class, 'index'])->name('gallery.index');
            Route::get('gallery/file', [GalleryController::class, 'file'])->name('gallery.file');
            Route::post('gallery/refresh', [GalleryController::class, 'refresh'])->name('gallery.refresh');
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

        // ===== Follow-ups =====
        // Aggregated, read-only views across every lead (list · calendar). Follow-up records
        // themselves are always created/managed from the Lead (routes nested under leads/{lead}).
        Route::middleware('permission:follow_ups.view')->group(function () {
            Route::get('follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index');
            Route::get('follow-ups/calendar', [FollowUpController::class, 'calendar'])->name('follow-ups.calendar');
        });
        Route::middleware('permission:follow_ups.create')->group(function () {
            Route::post('leads/{lead}/follow-ups', [LeadFollowUpController::class, 'store'])->whereNumber('lead')->name('leads.follow-ups.store');
        });
        // Mark Done (+ optionally schedule the next one in the same request).
        Route::middleware('permission:follow_ups.complete')->group(function () {
            Route::post('leads/{lead}/follow-ups/{followUp}/complete', [LeadFollowUpController::class, 'complete'])->whereNumber(['lead', 'followUp'])->name('leads.follow-ups.complete');
        });
        Route::middleware('permission:follow_ups.edit')->group(function () {
            Route::put('leads/{lead}/follow-ups/{followUp}', [LeadFollowUpController::class, 'update'])->whereNumber(['lead', 'followUp'])->name('leads.follow-ups.update');
            Route::post('leads/{lead}/follow-ups/{followUp}/cancel', [LeadFollowUpController::class, 'cancel'])->whereNumber(['lead', 'followUp'])->name('leads.follow-ups.cancel');
        });
        Route::middleware('permission:follow_ups.delete')->group(function () {
            Route::delete('leads/{lead}/follow-ups/{followUp}', [LeadFollowUpController::class, 'destroy'])->whereNumber(['lead', 'followUp'])->name('leads.follow-ups.destroy');
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
            Route::post('deals/{deal}/milestones', [DealController::class, 'milestoneStore'])->name('deals.milestones.store');
            Route::put('deals/{deal}/milestones/{milestone}', [DealController::class, 'milestoneUpdate'])->whereNumber(['deal', 'milestone'])->name('deals.milestones.update');
            Route::post('deals/{deal}/milestones/{milestone}/status', [DealController::class, 'milestoneStatus'])->whereNumber(['deal', 'milestone'])->name('deals.milestones.status');
            Route::delete('deals/{deal}/milestones/{milestone}', [DealController::class, 'milestoneDestroy'])->whereNumber(['deal', 'milestone'])->name('deals.milestones.destroy');
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
            Route::post('projects/{project}/milestones/import-deal', [ProjectController::class, 'milestonesImportFromDeal'])->whereNumber('project')->name('projects.milestones.import-deal');
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
            Route::get('project-config', [ProjectConfigController::class, 'index'])->name('project-config');
            Route::post('project-config/categories', [ProjectConfigController::class, 'categoryStore'])->name('project-config.categories.store');
            Route::put('project-config/categories/{category}', [ProjectConfigController::class, 'categoryUpdate'])->whereNumber('category')->name('project-config.categories.update');
            Route::patch('project-config/categories/{category}/menu', [ProjectConfigController::class, 'categoryMenu'])->whereNumber('category')->name('project-config.categories.menu');
            Route::delete('project-config/categories/{category}', [ProjectConfigController::class, 'categoryDestroy'])->whereNumber('category')->name('project-config.categories.destroy');
            Route::post('project-config/columns', [ProjectConfigController::class, 'columnStore'])->name('project-config.columns.store');
            Route::put('project-config/columns/{column}', [ProjectConfigController::class, 'columnUpdate'])->whereNumber('column')->name('project-config.columns.update');
            Route::delete('project-config/columns/{column}', [ProjectConfigController::class, 'columnDestroy'])->whereNumber('column')->name('project-config.columns.destroy');
        });

        // ===== Clients =====
        Route::middleware('permission:clients.view')->group(function () {
            Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        });
        // Activity logs — each page has its own permission (see Roles → Activity Logs).
        Route::middleware('permission:activity.client')->group(function () {
            Route::get('client-activity', [ClientActivityLogController::class, 'index'])->name('client-activity');
            Route::get('client-activity/details', [ClientActivityLogController::class, 'details'])->name('client-activity.details');
            // WhatsApp button: build a chat link and see how often it was followed.
            $wl = WhatsappLinkController::class;
            Route::get('whatsapp-links', [$wl, 'index'])->name('whatsapp-links');
            Route::post('whatsapp-links', [$wl, 'store'])->name('whatsapp-links.store');
            Route::put('whatsapp-links/{whatsappLink}', [$wl, 'update'])->whereNumber('whatsappLink')->name('whatsapp-links.update');
            Route::post('whatsapp-links/{whatsappLink}/toggle', [$wl, 'toggle'])->whereNumber('whatsappLink')->name('whatsapp-links.toggle');
            Route::delete('whatsapp-links/{whatsappLink}', [$wl, 'destroy'])->whereNumber('whatsappLink')->name('whatsapp-links.destroy');
            Route::get('client-activity/errors', [ClientActivityLogController::class, 'errors'])->name('client-activity.errors');
            Route::get('client-activity/clients', [ClientActivityLogController::class, 'clients'])->name('client-activity.clients');
            // Super-admin-only; the controller enforces that (permission alone is not enough here).
            Route::post('client-activity/clients/{client}/logout', [ClientActivityLogController::class, 'logoutClient'])->whereNumber('client')->name('client-activity.clients.logout');
        });
        // Activity → Cart: who added products to their cart on the website.
        Route::middleware('permission:activity.cart')->group(function () {
            Route::get('cart-activity', [CartActivityController::class, 'index'])->name('cart-activity');
        });
        // Blogs / Products share one route; the exact permission (activity.blogs / activity.products)
        // is checked in the controller since it depends on {type}.
        Route::get('client-activity/{type}', [ClientActivityLogController::class, 'content'])->whereIn('type', ['blogs', 'products'])->name('client-activity.content');
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
            Route::post('clients/import/dismiss', [ClientController::class, 'dismissImport'])->name('clients.import.dismiss');
        });
        Route::middleware('permission:clients.edit')->group(function () {
            Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->whereNumber('client')->name('clients.edit');
            Route::put('clients/{client}', [ClientController::class, 'update'])->whereNumber('client')->name('clients.update');
            Route::patch('clients/{client}/status', [ClientController::class, 'updateStatus'])->whereNumber('client')->name('clients.status');
            Route::post('clients/{client}/documents', [ClientController::class, 'storeDocument'])->whereNumber('client')->name('clients.documents.store');
            // Billing addresses — the same book the pay page writes into, now editable here.
            Route::post('clients/{client}/billing-addresses', [ClientController::class, 'storeBillingAddress'])->whereNumber('client')->name('clients.billing-addresses.store');
            Route::put('clients/{client}/billing-addresses/{address}', [ClientController::class, 'updateBillingAddress'])->whereNumber(['client', 'address'])->name('clients.billing-addresses.update');
            Route::patch('clients/{client}/billing-addresses/{address}/default', [ClientController::class, 'defaultBillingAddress'])->whereNumber(['client', 'address'])->name('clients.billing-addresses.default');
            Route::delete('clients/{client}/billing-addresses/{address}', [ClientController::class, 'destroyBillingAddress'])->whereNumber(['client', 'address'])->name('clients.billing-addresses.destroy');
            Route::delete('clients/{client}/documents/{document}', [ClientController::class, 'destroyDocument'])->whereNumber('client')->whereNumber('document')->name('clients.documents.destroy');
        });
        Route::middleware('permission:clients.delete')->group(function () {
            Route::delete('clients-bulk', [ClientController::class, 'bulkDestroy'])->name('clients.bulk-destroy');
            Route::delete('clients/{client}', [ClientController::class, 'destroy'])->whereNumber('client')->name('clients.destroy');
        });

        // ===== Support tickets =====
        Route::middleware('permission:tickets.view')->group(function () {
            Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
            Route::get('tickets/export', [TicketController::class, 'export'])->name('tickets.export');
            Route::get('tickets/{ticket}', [TicketController::class, 'show'])->whereNumber('ticket')->name('tickets.show');
        });
        Route::middleware('permission:tickets.create')->group(function () {
            Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
            Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
            Route::post('ticket-groups', [TicketController::class, 'storeGroup'])->name('tickets.groups.store');
            Route::post('ticket-types', [TicketController::class, 'storeType'])->name('tickets.types.store');
        });
        Route::post('tickets/{ticket}/replies', [TicketController::class, 'reply'])->whereNumber('ticket')->middleware('permission:tickets.reply')->name('tickets.reply');
        Route::middleware('permission:tickets.edit')->group(function () {
            Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->whereNumber('ticket')->name('tickets.status');
            Route::patch('tickets/{ticket}/assign', [TicketController::class, 'assign'])->whereNumber('ticket')->name('tickets.assign');
        });
        // CRM settings — one page, four tabs, and each tab is its own permission: the lists
        // behind Leads, behind the Deals pipeline, the client labels, and the shared product
        // catalogue are different amounts of trust. The page itself opens for anyone holding
        // at least one of them, and shows only the tabs they hold.
        Route::middleware('permission_any:leads.settings,deals.settings,clients.settings,product_categories.view')
            ->get('crm-settings', [CrmSettingController::class, 'index'])->name('crm-settings');

        Route::middleware('permission:leads.settings')->group(function () {
            Route::post('crm-settings/options', [CrmSettingController::class, 'storeOption'])->name('crm-settings.options.store');
            Route::patch('crm-settings/options/{option}', [CrmSettingController::class, 'updateOption'])->whereNumber('option')->name('crm-settings.options.update');
            Route::delete('crm-settings/options/{option}', [CrmSettingController::class, 'destroyOption'])->whereNumber('option')->name('crm-settings.options.destroy');
        });

        Route::middleware('permission:clients.settings')->group(function () {
            Route::post('crm-settings/client-labels', [CrmSettingController::class, 'storeClientLabel'])->name('crm-settings.client-labels.store');
            Route::patch('crm-settings/client-labels/{clientLabel}', [CrmSettingController::class, 'updateClientLabel'])->whereNumber('clientLabel')->name('crm-settings.client-labels.update');
            Route::delete('crm-settings/client-labels/{clientLabel}', [CrmSettingController::class, 'destroyClientLabel'])->whereNumber('clientLabel')->name('crm-settings.client-labels.destroy');
        });

        Route::post('crm-settings/product-categories', [CrmSettingController::class, 'storeProductCategory'])->middleware('permission:product_categories.create')->name('crm-settings.product-categories.store');
        Route::patch('crm-settings/product-categories/{productCategory}', [CrmSettingController::class, 'updateProductCategory'])->whereNumber('productCategory')->middleware('permission:product_categories.edit')->name('crm-settings.product-categories.update');
        Route::delete('crm-settings/product-categories/{productCategory}', [CrmSettingController::class, 'destroyProductCategory'])->whereNumber('productCategory')->middleware('permission:product_categories.delete')->name('crm-settings.product-categories.destroy');

        // Ticket settings (agents, types, reply templates) — separate, admin/manager-level gate.
        Route::middleware('permission:tickets.settings')->group(function () {
            Route::get('ticket-settings', [TicketSettingController::class, 'index'])->name('tickets.settings');
            Route::post('ticket-settings/agents', [TicketSettingController::class, 'storeAgent'])->name('tickets.settings.agents.store');
            Route::patch('ticket-settings/agents/{agent}', [TicketSettingController::class, 'updateAgent'])->name('tickets.settings.agents.update');
            Route::delete('ticket-settings/agents/{agent}', [TicketSettingController::class, 'destroyAgent'])->name('tickets.settings.agents.destroy');
            Route::post('ticket-settings/types', [TicketSettingController::class, 'storeType'])->name('tickets.settings.types.store');
            Route::patch('ticket-settings/types/{type}', [TicketSettingController::class, 'updateType'])->name('tickets.settings.types.update');
            Route::delete('ticket-settings/types/{type}', [TicketSettingController::class, 'destroyType'])->name('tickets.settings.types.destroy');
            Route::post('ticket-settings/templates', [TicketSettingController::class, 'storeTemplate'])->name('tickets.settings.templates.store');
            Route::patch('ticket-settings/templates/{template}', [TicketSettingController::class, 'updateTemplate'])->name('tickets.settings.templates.update');
            Route::delete('ticket-settings/templates/{template}', [TicketSettingController::class, 'destroyTemplate'])->name('tickets.settings.templates.destroy');
        });
        Route::delete('tickets/{ticket}', [TicketController::class, 'destroy'])->whereNumber('ticket')->name('tickets.destroy');

        // ===== Finance (internal money: wallets, banks, income, expenses, tax) =====
        $fin = FinanceController::class;
        Route::prefix('finance')->name('finance.')->group(function () use ($fin) {
            Route::get('/', [$fin, 'dashboard'])->name('dashboard');
            Route::get('wallets', [$fin, 'accounts'])->defaults('type', 'wallet')->name('wallets');
            Route::get('bank-accounts', [$fin, 'accounts'])->defaults('type', 'bank')->name('banks');
            Route::post('accounts', [$fin, 'accountStore'])->name('accounts.store');
            Route::put('accounts/{account}', [$fin, 'accountUpdate'])->whereNumber('account')->name('accounts.update');
            Route::delete('accounts/{account}', [$fin, 'accountDestroy'])->whereNumber('account')->name('accounts.destroy');

            Route::get('transactions', [$fin, 'transactions'])->name('transactions');
            Route::get('income', [$fin, 'transactions'])->defaults('only', 'income')->name('income');
            Route::get('expenses', [$fin, 'transactions'])->defaults('only', 'expense')->name('expenses');
            Route::post('transactions', [$fin, 'transactionStore'])->name('transactions.store');
            Route::put('transactions/{transaction}', [$fin, 'transactionUpdate'])->whereNumber('transaction')->name('transactions.update');
            Route::delete('transactions/{transaction}', [$fin, 'transactionDestroy'])->whereNumber('transaction')->name('transactions.destroy');

            Route::get('transfers', [$fin, 'transfers'])->name('transfers');
            Route::get('currency-conversion', [$fin, 'conversions'])->name('conversions');
            Route::post('transfers', [$fin, 'transferStore'])->name('transfers.store');

            Route::get('receivables', [$fin, 'receivables'])->name('receivables');
            Route::get('payables', [$fin, 'payables'])->name('payables');
            Route::post('payables', [$fin, 'payableStore'])->name('payables.store');
            Route::put('payables/{payable}', [$fin, 'payableUpdate'])->whereNumber('payable')->name('payables.update');
            Route::post('payables/{payable}/pay', [$fin, 'payablePay'])->whereNumber('payable')->name('payables.pay');
            Route::delete('payables/{payable}', [$fin, 'payableDestroy'])->whereNumber('payable')->name('payables.destroy');

            Route::get('vat-tax', [$fin, 'taxes'])->name('taxes');
            Route::post('vat-tax', [$fin, 'taxStore'])->name('taxes.store');
            Route::put('vat-tax/{tax}', [$fin, 'taxUpdate'])->whereNumber('tax')->name('taxes.update');
            Route::delete('vat-tax/{tax}', [$fin, 'taxDestroy'])->whereNumber('tax')->name('taxes.destroy');

            Route::get('reports', [$fin, 'reports'])->name('reports');
        });

        // ===== Products =====
        Route::middleware('permission:products.view')->group(function () {
            Route::get('products', [ProductController::class, 'index'])->name('products.index');
            Route::get('products/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');
        });
        // ===== Installation Plans (own permission set) =====
        $ip = InstallationPlanController::class;
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
                Route::get('{product}', [$ip, 'show'])->whereNumber('product')->name('show');
            });
            Route::middleware('permission:installation_plans.edit')->group(function () use ($ip) {
                Route::put('{product}/product', [$ip, 'productUpdate'])->whereNumber('product')->name('products.update');
            });
            Route::middleware('permission:installation_plans.delete')->group(function () use ($ip) {
                Route::delete('{product}/product', [$ip, 'productDestroy'])->whereNumber('product')->name('products.destroy');
            });
            Route::middleware('permission:installation_plans.edit')->group(function () use ($ip) {
                Route::post('{product}/status', [$ip, 'status'])->whereNumber('product')->name('status');
                Route::post('{product}/features/reorder', [$ip, 'featureReorder'])->whereNumber('product')->name('features.reorder');
                Route::put('{product}/features/{feature}', [$ip, 'featureUpdate'])->whereNumber(['product', 'feature'])->name('features.update');
                Route::post('{product}/features/{feature}/highlight', [$ip, 'featureHighlight'])->whereNumber(['product', 'feature'])->name('features.highlight');
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
            Route::post('products/reorder', [ProductController::class, 'reorder'])->name('products.reorder');
            Route::post('products/reorder-home', [ProductController::class, 'reorderHome'])->name('products.reorder-home');
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
            $ic = InvoiceConfigController::class;
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
            Route::patch('article-categories/{article_category}/status', [ArticleCategoryController::class, 'toggleActive'])->whereNumber('article_category')->name('article-categories.status');
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
        Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
    });
    Route::middleware('permission:careers.create')->group(function () {
        Route::get('jobs/create', [JobController::class, 'create'])->name('jobs.create');
        Route::post('jobs', [JobController::class, 'store'])->name('jobs.store');
    });
    Route::middleware('permission:careers.edit')->group(function () {
        Route::get('jobs/{job}/edit', [JobController::class, 'edit'])->whereNumber('job')->name('jobs.edit');
        Route::put('jobs/{job}', [JobController::class, 'update'])->whereNumber('job')->name('jobs.update');
    });
    Route::middleware('permission:careers.publish')->group(function () {
        Route::post('jobs/{job}/publish', [JobController::class, 'togglePublish'])->whereNumber('job')->name('jobs.publish');
    });
    Route::middleware('permission:careers.delete')->group(function () {
        Route::delete('jobs/{job}', [JobController::class, 'destroy'])->whereNumber('job')->name('jobs.destroy');
    });

    // ---- Promotion (site-wide promo banner, draft → publish workflow) ----
    Route::middleware('permission:promotion.view')->group(function () {
        Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
    });
    Route::middleware('permission:promotion.create')->group(function () {
        Route::get('promotions/create', [PromotionController::class, 'create'])->name('promotions.create');
        Route::post('promotions', [PromotionController::class, 'store'])->name('promotions.store');
    });
    Route::middleware('permission:promotion.edit')->group(function () {
        Route::get('promotions/{promotion}/edit', [PromotionController::class, 'edit'])->whereNumber('promotion')->name('promotions.edit');
        Route::put('promotions/{promotion}', [PromotionController::class, 'update'])->whereNumber('promotion')->name('promotions.update');
    });
    Route::middleware('permission:promotion.publish')->group(function () {
        Route::post('promotions/{promotion}/publish', [PromotionController::class, 'togglePublish'])->whereNumber('promotion')->name('promotions.publish');
    });
    Route::middleware('permission:promotion.delete')->group(function () {
        Route::delete('promotions/{promotion}', [PromotionController::class, 'destroy'])->whereNumber('promotion')->name('promotions.destroy');
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
            Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs');
            Route::get('activity-logs/{employee}', [ActivityLogController::class, 'show'])->whereNumber('employee')->name('activity-logs.show');
        });

        // ---- Activity → CodeCanyon (official Envato API only; no scraping) ----
        Route::middleware('permission:codecanyon.view')->group(function () {
            $cc = CodeCanyonController::class;
            Route::get('codecanyon', [$cc, 'index'])->name('codecanyon.index');
            Route::get('codecanyon/authors/{author}', [$cc, 'author'])->whereNumber('author')->name('codecanyon.author');
            Route::get('codecanyon/products/{product}', [$cc, 'product'])->whereNumber('product')->name('codecanyon.product');
        });
        Route::middleware('permission:codecanyon.manage')->group(function () {
            $cc = CodeCanyonController::class;
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
            $cc = CodeCanyonController::class;
            Route::get('codecanyon-settings', [$cc, 'settings'])->name('codecanyon-settings');
            Route::put('codecanyon-settings', [$cc, 'saveSettings'])->name('codecanyon-settings.save');

            // Meta Conversions API — server-side pixel events.
            Route::get('meta-capi', [MetaCapiController::class, 'index'])->name('meta-capi');
            Route::post('meta-capi', [MetaCapiController::class, 'update'])->name('meta-capi.update');
            Route::post('meta-capi/test', [MetaCapiController::class, 'test'])->name('meta-capi.test');

        });

        // Settings → Bin (recoverable clients + invoices; super admin only, enforced in the controller).
        Route::get('bin', [BinController::class, 'index'])->name('bin');
        Route::post('bin/clients/{id}/restore', [BinController::class, 'restoreClient'])->whereNumber('id')->name('bin.clients.restore');
        Route::post('bin/projects/{id}/restore', [BinController::class, 'restoreProject'])->whereNumber('id')->name('bin.projects.restore');
        Route::delete('bin/projects/{id}', [BinController::class, 'forceDeleteProject'])->whereNumber('id')->name('bin.projects.force-delete');
        Route::delete('bin/projects/empty', [BinController::class, 'emptyProjects'])->name('bin.projects.empty');
        Route::delete('bin/clients/{id}', [BinController::class, 'forceDeleteClient'])->whereNumber('id')->name('bin.clients.force-delete');
        Route::post('bin/clients/restore', [BinController::class, 'bulkRestoreClients'])->name('bin.clients.bulk-restore');
        Route::delete('bin/clients', [BinController::class, 'bulkForceDeleteClients'])->name('bin.clients.bulk-delete');
        Route::post('bin/invoices/restore', [BinController::class, 'bulkRestoreInvoices'])->name('bin.invoices.bulk-restore');
        Route::delete('bin/invoices', [BinController::class, 'bulkForceDeleteInvoices'])->name('bin.invoices.bulk-delete');
        // Empty the Trash — permanently delete everything in a tab.
        Route::delete('bin/clients/empty', [BinController::class, 'emptyClients'])->name('bin.clients.empty');
        Route::delete('bin/invoices/empty', [BinController::class, 'emptyInvoices'])->name('bin.invoices.empty');
        Route::delete('bin/whatsapp/empty', [BinController::class, 'emptyWhatsapp'])->name('bin.whatsapp.empty');

        /* ---- Email Management ---------------------------------------------------- */
        Route::prefix('email')->name('email.')->group(function () {
            $ec = EmailConfigController::class;
            Route::get('configs', [$ec, 'index'])->name('configs');
            Route::post('configs', [$ec, 'store'])->name('configs.store');
            Route::put('configs/{config}', [$ec, 'update'])->whereNumber('config')->name('configs.update');
            Route::delete('configs/{config}', [$ec, 'destroy'])->whereNumber('config')->name('configs.destroy');
            Route::post('configs/{config}/default', [$ec, 'makeDefault'])->whereNumber('config')->name('configs.default');
            Route::post('configs/{config}/test', [$ec, 'test'])->whereNumber('config')->name('configs.test');
            Route::post('configs/{config}/send-test', [$ec, 'sendTest'])->whereNumber('config')->name('configs.send-test');

            $et = EmailTemplateController::class;
            Route::get('templates', [$et, 'index'])->name('templates');
            Route::post('templates', [$et, 'store'])->name('templates.store');
            Route::get('templates/{template}/edit', [$et, 'edit'])->whereNumber('template')->name('templates.edit');
            Route::put('templates/{template}', [$et, 'update'])->whereNumber('template')->name('templates.update');
            Route::delete('templates/{template}', [$et, 'destroy'])->whereNumber('template')->name('templates.destroy');
            Route::get('templates/{template}/preview', [$et, 'preview'])->whereNumber('template')->name('templates.preview');
            Route::post('templates/{template}/send-test', [$et, 'sendTest'])->whereNumber('template')->name('templates.send-test');
            Route::post('templates/{template}/toggle', [$et, 'toggle'])->whereNumber('template')->name('templates.toggle');

            $el = EmailLogController::class;
            Route::get('queue', [$el, 'queue'])->name('queue');
            Route::post('queue/retry-all', [$el, 'retryAll'])->name('queue.retry-all');
            Route::get('logs', [$el, 'index'])->name('logs');
            Route::get('logs/{log}', [$el, 'show'])->whereNumber('log')->name('logs.show');
            Route::get('logs/{log}/body', [$el, 'body'])->whereNumber('log')->name('logs.body');
            Route::post('logs/{log}/retry', [$el, 'retry'])->whereNumber('log')->name('logs.retry');
            Route::post('logs/{log}/cancel', [$el, 'cancel'])->whereNumber('log')->name('logs.cancel');
            Route::post('logs/{log}/resend', [$el, 'resend'])->whereNumber('log')->name('logs.resend');
            Route::delete('logs/{log}', [$el, 'destroy'])->whereNumber('log')->name('logs.destroy');
            Route::get('suppressions', [$el, 'suppressions'])->name('suppressions');
            Route::post('suppressions', [$el, 'addSuppression'])->name('suppressions.store');
            Route::delete('suppressions/{suppression}', [$el, 'removeSuppression'])->whereNumber('suppression')->name('suppressions.destroy');

            $ea = EmailAnalyticsController::class;
            Route::get('analytics', [$ea, 'index'])->name('analytics');
            Route::get('rules', [$ea, 'rules'])->name('rules');
            Route::post('rules', [$ea, 'updateRules'])->name('rules.update');

            $ecm = EmailCampaignController::class;
            Route::get('campaigns', [$ecm, 'index'])->name('campaigns');
            Route::get('campaigns/create', [$ecm, 'create'])->name('campaigns.create');
            Route::post('campaigns', [$ecm, 'store'])->name('campaigns.store');
            Route::get('campaigns/audience-count', [$ecm, 'audienceCount'])->name('campaigns.audience-count');
            Route::get('campaigns/{campaign}', [$ecm, 'show'])->whereNumber('campaign')->name('campaigns.show');
            Route::get('campaigns/{campaign}/edit', [$ecm, 'edit'])->whereNumber('campaign')->name('campaigns.edit');
            Route::put('campaigns/{campaign}', [$ecm, 'update'])->whereNumber('campaign')->name('campaigns.update');
            Route::post('campaigns/{campaign}/cancel', [$ecm, 'cancel'])->whereNumber('campaign')->name('campaigns.cancel');
            Route::post('campaigns/{campaign}/send-test', [$ecm, 'sendTest'])->whereNumber('campaign')->name('campaigns.send-test');
            Route::delete('campaigns/{campaign}', [$ecm, 'destroy'])->whereNumber('campaign')->name('campaigns.destroy');
        });

    });

    // ---- HR (permission-gated: super admin can grant these to employee roles) ----
    Route::middleware('staff')->group(function () {
        Route::middleware('permission:employees.view')->group(function () {
            Route::post('staff-designations', [StaffController::class, 'storeDesignation'])->name('staff.designations.store');
            Route::post('staff-departments', [StaffController::class, 'storeDepartment'])->name('staff.departments.store');
            Route::resource('staff', StaffController::class)->except('show');
            Route::get('staff/{staff}', [StaffController::class, 'show'])->whereNumber('staff')->name('staff.show');

            // Employee profile tabs that own data: documents, payroll and the shift roster.
            Route::post('staff/{staff}/documents', [StaffController::class, 'documentStore'])->whereNumber('staff')->name('staff.documents.store');
            Route::delete('staff/{staff}/documents/{document}', [StaffController::class, 'documentDestroy'])->whereNumber(['staff', 'document'])->name('staff.documents.destroy');
            Route::post('staff/{staff}/payroll', [StaffController::class, 'payrollStore'])->whereNumber('staff')->name('staff.payroll.store');
            Route::delete('staff/{staff}/payroll/{payroll}', [StaffController::class, 'payrollDestroy'])->whereNumber(['staff', 'payroll'])->name('staff.payroll.destroy');
            Route::post('staff/{staff}/shifts', [StaffController::class, 'shiftStore'])->whereNumber('staff')->name('staff.shifts.store');
            Route::delete('staff/{staff}/shifts/{shift}', [StaffController::class, 'shiftDestroy'])->whereNumber(['staff', 'shift'])->name('staff.shifts.destroy');
        });
        Route::middleware('permission:designations.view')->group(function () {
            Route::resource('designations', DesignationController::class)->only(['index', 'store', 'update', 'destroy']);
        });
        Route::middleware('permission:departments.view')->group(function () {
            Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
        });

        // ===== Attendance (biometric / web / login / manual) =====
        $att = AttendanceController::class;
        Route::middleware('permission:attendance.view')->group(function () use ($att) {
            Route::get('attendance', [$att, 'index'])->name('attendance.index');
            Route::get('attendance/history', [$att, 'history'])->name('attendance.history');
            // Own check-in/out: any panel user with attendance.view may punch for themselves.
            Route::post('attendance/check-in', [$att, 'checkIn'])->name('attendance.check-in');
            Route::post('attendance/check-out', [$att, 'checkOut'])->name('attendance.check-out');
        });
        Route::middleware('permission:attendance.create')->group(function () use ($att) {
            Route::post('attendance/manual', [$att, 'manualStore'])->name('attendance.manual');
        });
        Route::middleware('permission:attendance.delete')->group(function () use ($att) {
            Route::delete('attendance/{attendance}', [$att, 'destroy'])->whereNumber('attendance')->name('attendance.destroy');
        });
        Route::middleware('permission:attendance.settings')->group(function () use ($att) {
            Route::get('attendance/settings', [$att, 'settings'])->name('attendance.settings');
            Route::put('attendance/settings', [$att, 'settingsUpdate'])->name('attendance.settings.update');
            Route::get('attendance/devices', [$att, 'devices'])->name('attendance.devices');
            Route::post('attendance/devices', [$att, 'deviceStore'])->name('attendance.devices.store');
            Route::put('attendance/devices/{device}', [$att, 'deviceUpdate'])->whereNumber('device')->name('attendance.devices.update');
            Route::delete('attendance/devices/{device}', [$att, 'deviceDestroy'])->whereNumber('device')->name('attendance.devices.destroy');
            Route::post('attendance/devices/{device}/import', [$att, 'deviceImport'])->whereNumber('device')->name('attendance.devices.import');
            Route::post('attendance/biometric-id', [$att, 'assignBiometricId'])->name('attendance.biometric-id');
        });

        // Leave — employees request their own; approvers review.
        Route::middleware('permission:leave.view')->group(function () {
            Route::get('leaves', [LeaveController::class, 'index'])->name('leaves.index');
            Route::delete('leaves/{leave}', [LeaveController::class, 'destroy'])->whereNumber('leave')->name('leaves.destroy');
            Route::patch('leaves/{leave}/status', [LeaveController::class, 'updateStatus'])->whereNumber('leave')->name('leaves.status');
        });
        Route::middleware('permission:leave.create')->group(function () {
            Route::get('leaves/create', [LeaveController::class, 'create'])->name('leaves.create');
            Route::post('leaves', [LeaveController::class, 'store'])->name('leaves.store');
        });
    });
});
