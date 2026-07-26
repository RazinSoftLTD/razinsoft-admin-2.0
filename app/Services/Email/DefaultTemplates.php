<?php

namespace App\Services\Email;

/**
 * The templates the system ships with.
 *
 * Bodies are deliberately plain: a single centred card, inline styles only, no <table> layout
 * tricks, no images. That renders the same everywhere and gives spam filters nothing to object
 * to — an admin can then make them as elaborate as they like.
 *
 * The wording avoids the words that trip filters ("free", "act now", "guaranteed", ALL CAPS,
 * rows of exclamation marks). Subjects say what the mail is, nothing more.
 */
class DefaultTemplates
{
    /**
     * @return array<int, array{key: string, name: string, category: string, subject: string, description: string, variables: string, body: string}>
     */
    public static function all(): array
    {
        return [
            // ---- Account -------------------------------------------------
            self::make('welcome_client', 'Welcome Email', 'Account',
                'Welcome to {{company_name}}',
                'Sent when a client account is created.',
                'customer_name, login_url',
                '<p>Hi {{customer_name}},</p>
                 <p>Your {{company_name}} account is ready. You can sign in any time to follow your projects, invoices and support tickets.</p>'
                .self::button('{{login_url}}', 'Sign in')
                .'<p>If you did not expect this email, you can ignore it.</p>'),

            self::make('email_verification', 'Email Verification', 'Account',
                'Confirm your email address',
                'Sent to confirm a new address belongs to the person using it.',
                'customer_name, verification_url',
                '<p>Hi {{customer_name}},</p>
                 <p>Please confirm this email address so we know where to send your account notifications.</p>'
                .self::button('{{verification_url}}', 'Confirm email address')
                .'<p>This link expires in 60 minutes. If you did not create an account, no action is needed.</p>'),

            self::make('password_reset', 'Password Reset', 'Account',
                'Reset your password',
                'Sent when someone asks to reset their password.',
                'customer_name, reset_url',
                '<p>Hi {{customer_name}},</p>
                 <p>We received a request to reset the password for your {{company_name}} account.</p>'
                .self::button('{{reset_url}}', 'Choose a new password')
                .'<p>This link expires in 60 minutes. If you did not ask for this, your password has not changed and you can ignore this email.</p>'),

            // ---- Billing --------------------------------------------------
            self::make('invoice_sent', 'Invoice', 'Billing',
                'Invoice {{invoice_number}} from {{company_name}}',
                'Sent when an invoice is issued to a client.',
                'customer_name, invoice_number, invoice_total, due_date, invoice_url',
                '<p>Hi {{customer_name}},</p>
                 <p>Invoice <strong>{{invoice_number}}</strong> for <strong>{{invoice_total}}</strong> is ready. It is due on {{due_date}}.</p>'
                .self::button('{{invoice_url}}', 'View invoice')
                .'<p>Please reply to this email if anything on it looks wrong.</p>'),

            self::make('payment_received', 'Payment Received', 'Billing',
                'Payment received for invoice {{invoice_number}}',
                'Sent when a payment is recorded against an invoice.',
                'customer_name, invoice_number, amount_paid, invoice_url',
                '<p>Hi {{customer_name}},</p>
                 <p>We have received your payment of <strong>{{amount_paid}}</strong> for invoice {{invoice_number}}. Thank you.</p>'
                .self::button('{{invoice_url}}', 'View receipt')),

            self::make('payment_failed', 'Payment Failed', 'Billing',
                'We could not process your payment for {{invoice_number}}',
                'Sent when an online payment attempt fails.',
                'customer_name, invoice_number, invoice_url',
                '<p>Hi {{customer_name}},</p>
                 <p>Your payment for invoice {{invoice_number}} did not go through. No money has been taken.</p>'
                .self::button('{{invoice_url}}', 'Try again')
                .'<p>If the problem continues, reply to this email and we will help.</p>'),

            self::make('refund_processed', 'Refund Processed', 'Billing',
                'Your refund for {{invoice_number}} has been processed',
                'Sent when a refund is issued.',
                'customer_name, invoice_number, refund_amount',
                '<p>Hi {{customer_name}},</p>
                 <p>We have refunded <strong>{{refund_amount}}</strong> against invoice {{invoice_number}}. Depending on your bank it can take a few working days to appear.</p>'),

            self::make('order_confirmation', 'Order Confirmation', 'Billing',
                'Your order {{order_number}} is confirmed',
                'Sent when an order is placed.',
                'customer_name, order_number, order_total, order_url',
                '<p>Hi {{customer_name}},</p>
                 <p>Thank you for your order <strong>{{order_number}}</strong> ({{order_total}}). We will email you again as soon as it is ready.</p>'
                .self::button('{{order_url}}', 'View order')),

            self::make('license_delivered', 'License Delivered', 'Billing',
                'Your license key for {{product_name}}',
                'Sent when a purchased license is issued.',
                'customer_name, product_name, license_key, download_url',
                '<p>Hi {{customer_name}},</p>
                 <p>Here is your license key for <strong>{{product_name}}</strong>:</p>
                 <p style="margin:16px 0;padding:12px 16px;background:#f3f4f6;border-radius:8px;font-family:monospace;font-size:14px;word-break:break-all">{{license_key}}</p>'
                .self::button('{{download_url}}', 'Download')
                .'<p>Keep this email — the key is needed to activate the product.</p>'),

            self::make('subscription_activated', 'Subscription Activated', 'Billing',
                'Your {{plan_name}} subscription is active',
                'Sent when a subscription starts or renews.',
                'customer_name, plan_name, renews_on',
                '<p>Hi {{customer_name}},</p>
                 <p>Your <strong>{{plan_name}}</strong> subscription is active. It renews on {{renews_on}}.</p>'),

            self::make('subscription_expired', 'Subscription Expired', 'Billing',
                'Your {{plan_name}} subscription has ended',
                'Sent when a subscription lapses.',
                'customer_name, plan_name, renew_url',
                '<p>Hi {{customer_name}},</p>
                 <p>Your <strong>{{plan_name}}</strong> subscription has ended, so the features it included are no longer available.</p>'
                .self::button('{{renew_url}}', 'Renew subscription')),

            self::make('trial_ending', 'Trial Ending', 'Billing',
                'Your trial ends on {{end_date}}',
                'Sent a few days before a trial runs out.',
                'customer_name, plan_name, end_date, upgrade_url',
                '<p>Hi {{customer_name}},</p>
                 <p>Your {{plan_name}} trial ends on <strong>{{end_date}}</strong>. Choose a plan before then to keep everything you have set up.</p>'
                .self::button('{{upgrade_url}}', 'Choose a plan')),

            // ---- Support --------------------------------------------------
            self::make('ticket_created', 'Support Ticket Created', 'Support',
                'Ticket {{ticket_number}}: {{ticket_subject}}',
                'Sent when a support ticket is opened.',
                'customer_name, ticket_number, ticket_subject, ticket_url',
                '<p>Hi {{customer_name}},</p>
                 <p>We have received your request and opened ticket <strong>{{ticket_number}}</strong>. Our team will reply here.</p>'
                .self::button('{{ticket_url}}', 'View ticket')),

            self::make('ticket_reply', 'Support Reply', 'Support',
                'Re: {{ticket_subject}} ({{ticket_number}})',
                'Sent when an agent replies to a ticket.',
                'customer_name, ticket_number, ticket_subject, reply_body, ticket_url',
                '<p>Hi {{customer_name}},</p>
                 <p>There is a new reply on your ticket <strong>{{ticket_number}}</strong>:</p>
                 <div style="margin:16px 0;padding:14px 16px;background:#f9fafb;border-left:3px solid #d1d5db;border-radius:4px">{{reply_body}}</div>'
                .self::button('{{ticket_url}}', 'Reply')),

            self::make('ticket_closed', 'Support Closed', 'Support',
                'Ticket {{ticket_number}} has been closed',
                'Sent when a ticket is resolved and closed.',
                'customer_name, ticket_number, ticket_subject, ticket_url',
                '<p>Hi {{customer_name}},</p>
                 <p>Your ticket <strong>{{ticket_number}}</strong> has been closed. If the problem comes back, reply to this email and we will reopen it.</p>'
                .self::button('{{ticket_url}}', 'View ticket')),

            // ---- Projects -------------------------------------------------
            self::make('project_created', 'Project Created', 'Projects',
                'Your project {{project_name}} has started',
                'Sent when a project is created for a client.',
                'customer_name, project_name, project_url',
                '<p>Hi {{customer_name}},</p>
                 <p>We have started work on <strong>{{project_name}}</strong>. You can follow its progress and milestones at any time.</p>'
                .self::button('{{project_url}}', 'View project')),

            self::make('project_updated', 'Project Updated', 'Projects',
                'Update on {{project_name}}',
                'Sent when a project milestone or status changes.',
                'customer_name, project_name, update_note, project_url',
                '<p>Hi {{customer_name}},</p>
                 <p>There is an update on <strong>{{project_name}}</strong>:</p>
                 <div style="margin:16px 0;padding:14px 16px;background:#f9fafb;border-left:3px solid #d1d5db;border-radius:4px">{{update_note}}</div>'
                .self::button('{{project_url}}', 'View project')),

            self::make('project_completed', 'Project Completed', 'Projects',
                '{{project_name}} is complete',
                'Sent when a project is marked complete.',
                'customer_name, project_name, project_url',
                '<p>Hi {{customer_name}},</p>
                 <p><strong>{{project_name}}</strong> is complete. Thank you for working with us.</p>'
                .self::button('{{project_url}}', 'View project')),

            self::make('meeting_booked', 'Meeting Invitation', 'Projects',
                'Your meeting with {{company_name}} is confirmed',
                'Sent when a meeting is booked.',
                'customer_name, meeting_date, meeting_time, meeting_link',
                '<p>Hi {{customer_name}},</p>
                 <p>Your meeting is confirmed for <strong>{{meeting_date}}</strong> at <strong>{{meeting_time}}</strong>.</p>'
                .self::button('{{meeting_link}}', 'Join the meeting')
                .'<p>If you need to move it, reply to this email.</p>'),

            // ---- Marketing / system ---------------------------------------
            self::make('newsletter', 'Newsletter', 'Marketing',
                '{{newsletter_subject}}',
                'The shell used for newsletters. Marketing mail carries an unsubscribe link.',
                'customer_name, newsletter_subject, newsletter_body',
                '<p>Hi {{customer_name}},</p>
                 <div>{{newsletter_body}}</div>'),

            self::make('marketing_campaign', 'Marketing Campaign', 'Marketing',
                '{{campaign_subject}}',
                'The shell used for one-off campaigns.',
                'customer_name, campaign_subject, campaign_body',
                '<p>Hi {{customer_name}},</p>
                 <div>{{campaign_body}}</div>'),

            self::make('maintenance_notice', 'Maintenance Notice', 'System',
                'Planned maintenance on {{maintenance_date}}',
                'Sent before planned downtime.',
                'customer_name, maintenance_date, maintenance_window, maintenance_note',
                '<p>Hi {{customer_name}},</p>
                 <p>We have planned maintenance on <strong>{{maintenance_date}}</strong>, {{maintenance_window}}. The service may be briefly unavailable during that window.</p>
                 <p>{{maintenance_note}}</p>'),
        ];
    }

    /** A primary button. Inline styles only — every mail client understands this. */
    private static function button(string $url, string $label): string
    {
        return '<p style="margin:24px 0"><a href="'.$url.'" '
            .'style="display:inline-block;padding:12px 22px;background:#4f46e5;color:#ffffff;'
            .'text-decoration:none;border-radius:8px;font-weight:600;font-size:14px">'.$label.'</a></p>';
    }

    private static function make(string $key, string $name, string $category, string $subject, string $description, string $variables, string $body): array
    {
        return compact('key', 'name', 'category', 'subject', 'description', 'variables') + ['body' => $body];
    }

    /**
     * Wrap a body in the shared shell: a centred card, a preheader, and a footer.
     *
     * The preheader is the grey line a mail client shows next to the subject. Left empty it picks
     * up whatever text comes first, which usually reads badly.
     */
    public static function wrap(string $body, string $preheader = ''): string
    {
        $year = '{{current_year}}';

        return <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>{{company_name}}</title>
        </head>
        <body style="margin:0;padding:0;background:#f5f6fa;font-family:Arial,Helvetica,sans-serif;color:#1f2937">
        <div style="display:none;max-height:0;overflow:hidden;opacity:0">{$preheader}</div>
        <div style="padding:28px 16px">
          <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;padding:32px">
            <h1 style="margin:0 0 20px;font-size:18px;font-weight:700;color:#111827">{{company_name}}</h1>
            <div style="font-size:14px;line-height:1.7;color:#374151">
        {$body}
            </div>
          </div>
          <p style="max-width:560px;margin:16px auto 0;text-align:center;font-size:12px;line-height:1.6;color:#9ca3af">
            &copy; {$year} {{company_name}}<br>
            You are receiving this because you have an account with us.
          </p>
        </div>
        </body>
        </html>
        HTML;
    }
}
