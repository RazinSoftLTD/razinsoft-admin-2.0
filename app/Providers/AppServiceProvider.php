<?php

namespace App\Providers;

use App\Models\EmailSetting;
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
        // Apply the admin-configured SMTP settings over config/mail.php (if the table exists).
        try {
            if (Schema::hasTable('email_settings')) {
                EmailSetting::current()->apply();
            }
        } catch (\Throwable $e) {
            // DB not ready (e.g. during install) — fall back to .env mail config.
        }

        // Password-reset emails link to the website's reset page (not the API host).
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $frontend = rtrim(config('app.frontend_url', config('services.frontend_url', 'http://localhost:3000')), '/');

            return $frontend.'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset());
        });
    }
}
