<?php

namespace App\Providers;

use App\Models\EmailConfig;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Point Laravel's own mailer at the default account from Email Settings → Configuration.
        //
        // Mail sent through EmailDispatcher does not need this — the worker picks its own account
        // per message. This is for anything that still reaches for `Mail::` directly, including
        // the framework's own notifications: without it they fall back to MAIL_MAILER in .env,
        // which is `log` here, so they would be written to a file and never sent.
        try {
            if (Schema::hasTable('email_configs')) {
                EmailConfig::pick()?->makeDefaultMailer();
            }
        } catch (\Throwable $e) {
            // DB not ready (e.g. during install) — fall back to .env mail config.
        }

        // Mirror invoice payments into the Finance module's income, whichever screen recorded them.
        \App\Models\InvoicePayment::observe(\App\Observers\InvoicePaymentObserver::class);

        // Password-reset emails link to the website's reset page (not the API host).
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $frontend = rtrim(config('app.frontend_url', config('services.frontend_url', 'http://localhost:3000')), '/');

            return $frontend.'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset());
        });
    }
}
