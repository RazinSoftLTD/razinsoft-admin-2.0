<?php

namespace App\Support;

use App\Models\EmailSetting;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Mail;

class TemplateMailer
{
    /** Send an email built from a stored template. Returns false if the template is missing/inactive. */
    public static function send(string $to, string $templateKey, array $data = []): bool
    {
        EmailSetting::current()->apply();

        $rendered = EmailTemplate::render($templateKey, $data);
        if (! $rendered) {
            return false;
        }

        Mail::html($rendered['body'], function ($message) use ($to, $rendered) {
            $message->to($to)->subject($rendered['subject']);
        });

        return true;
    }

    /** Send a plain test email to verify SMTP settings. */
    public static function sendTest(string $to): void
    {
        EmailSetting::current()->apply();

        Mail::html(
            '<p>This is a test email from your RazinSoft admin panel. Your SMTP settings are working. ✅</p>',
            fn ($message) => $message->to($to)->subject('RazinSoft SMTP test')
        );
    }
}
