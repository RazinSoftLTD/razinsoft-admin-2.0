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
     * Footer line for unsolicited outreach. The shell's default claims the
     * reader has an account with us, which is not true of a business found on
     * Maps — and it would sit directly beneath the template's own, accurate
     * disclosure.
     */
    private const OUTREACH_NOTE = 'Sent because this business is listed publicly on Google Maps. It is not a newsletter and you are not on a mailing list.';

    /**
     * @return array<int, array{key: string, name: string, category: string, subject: string, description: string, variables: string, body: string}>
     */
    public static function all(): array
    {
        return array_merge(self::mapsOutreach(), [
            // ---- Account -------------------------------------------------
            self::make('welcome_client', 'Welcome Email', 'Account',
                'Welcome to {{company_name}}',
                'Your account is ready — here are your details and a link to sign in.',
                'customer_name, customer_email, registration_date, login_url',
                self::welcomeBody(),
                fullBleed: true),

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

            self::make('invoice_reminder', 'Invoice Reminder', 'Billing',
                'Reminder: invoice {{invoice_number}} is still open',
                'Sent when a client is reminded about an unpaid invoice.',
                'customer_name, invoice_number, invoice_total, due_date, invoice_url',
                '<p>Hi {{customer_name}},</p>
                 <p>Invoice <strong>{{invoice_number}}</strong> for <strong>{{invoice_total}}</strong> is still showing as unpaid. It was due on {{due_date}}.</p>'
                .self::button('{{invoice_url}}', 'Pay invoice')
                .'<p>If you have already paid, or something on the invoice looks wrong, reply to this email and we will sort it out.</p>'),

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
                'Your order {{order_number}} is ready',
                'Sent when an order is paid and its items have been made available.',
                'customer_name, order_number, order_total, order_items, order_url',
                '<p>Hi {{customer_name}},</p>
                 <p>Your order <strong>{{order_number}}</strong> ({{order_total}}) is paid and ready. You now have access to:</p>
                 <ul style="margin:12px 0;padding-left:20px">{{order_items}}</ul>
                 <p>Your invoice, license keys and downloads are in your account.</p>'
                .self::button('{{order_url}}', 'Open my account')),

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

            /*
             * First contact with a business found on Google Maps.
             *
             * Written to be the kind of mail a small business owner might actually
             * answer: it says where we got their details, what we do in one line,
             * and asks a question instead of pitching. No offers, no urgency, no
             * attachments — all of which mark a message as bulk to both filters
             * and readers.
             *
             * The unsubscribe line is part of the body on purpose. This is
             * unsolicited mail, so an opt-out is not optional, and burying it in a
             * shared footer makes it easy to lose when the template is edited.
             */
            self::make('maps_lead_outreach', 'Business Outreach (Maps Lead)', 'Marketing',
                'Software for {{business_name}}?',
                'First contact with a business collected from Google Maps. Carries a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                '<p>Hello,</p>
                 <p>I came across <strong>{{business_name}}</strong> on Google Maps while looking at
                    {{business_category}} businesses in {{business_city}}, and thought I would get in touch.</p>
                 <p>We are {{company_name}} — we build software for businesses like yours: point of sale
                    and inventory, booking, and custom systems.</p>
                 <p>Is managing this side of the business currently costing you time? If so I am happy to
                    show you what we have built for similar businesses. If not, no reply needed.</p>
                 <p>Best regards,<br>{{company_name}}</p>
                 <p style="margin-top:26px;padding-top:14px;border-top:1px solid #e2e8f0;
                           font-size:12px;color:#94a3b8">
                    You received this because {{business_name}} is listed publicly on Google Maps.
                    <a href="{{unsubscribe_url}}" style="color:#94a3b8">Unsubscribe</a>
                    and we will not contact this address again.
                 </p>'),

            self::make('maintenance_notice', 'Maintenance Notice', 'System',
                'Planned maintenance on {{maintenance_date}}',
                'Sent before planned downtime.',
                'customer_name, maintenance_date, maintenance_window, maintenance_note',
                '<p>Hi {{customer_name}},</p>
                 <p>We have planned maintenance on <strong>{{maintenance_date}}</strong>, {{maintenance_window}}. The service may be briefly unavailable during that window.</p>
                 <p>{{maintenance_note}}</p>'),
        ]);
    }

    /**
     * The FoodGet pitch sent to restaurants.
     *
     * Laid out with tables and inline styles because that is what mail clients
     * render consistently; the feature strip is styled cells rather than icons,
     * since images are blocked by default in most clients and a row of broken
     * boxes reads worse than plain labels.
     *
     * Two links matter and both are rewritten for click tracking on the way out:
     * the product link, and the WhatsApp button. A click on either is what tells
     * us the lead actually reached us.
     *
     * The closing line is a working unsubscribe, not an invitation to reply.
     * This is unsolicited mail, so a one-click opt-out is what keeps it lawful
     * and keeps the sending domain out of trouble.
     */
    private static function foodGetBody(): string
    {
        return <<<'HTML'
<p style="margin:0 0 18px">Hello,</p>

<p style="margin:0 0 18px">
  I recently came across <strong>{{business_name}}</strong> and wanted to introduce
  <strong style="color:#2563eb">FoodGet</strong>.
</p>

<p style="margin:0 0 14px">
  FoodGet is a complete Restaurant Management System designed to help restaurants
  manage everything from one place, including:
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="margin:0 0 22px;background:#eff6ff;border-radius:12px">
  <tr>
    <td style="padding:18px 22px;font-size:14px;line-height:2;color:#1e3a8a">
      &#10004;&nbsp; POS &amp; Billing<br>
      &#10004;&nbsp; Online Orders<br>
      &#10004;&nbsp; Kitchen Management<br>
      &#10004;&nbsp; Table Reservations<br>
      &#10004;&nbsp; Inventory Management<br>
      &#10004;&nbsp; Sales &amp; Financial Reports<br>
      &#10004;&nbsp; Customer &amp; Loyalty Management
    </td>
  </tr>
</table>

<p style="margin:0 0 18px">
  Whether you run a small cafe or a multi-branch restaurant, FoodGet helps save
  time, reduce operational costs, and improve customer service.
</p>

<p style="margin:0 0 18px">
  I would be glad to offer you a
  <a href="https://www.razinsoft.com" style="color:#2563eb;font-weight:700;text-decoration:underline">live demo</a>
  at no cost, so you can see how FoodGet simplifies your day-to-day operations.
</p>

<p style="margin:0 0 26px">
  Would you be available for a quick <strong>10-minute demo</strong> this week?
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="margin:0 0 26px;background:#ecfdf5;border-radius:12px">
  <tr>
    <td style="padding:18px 20px">
      <p style="margin:0 0 3px;font-size:15px;font-weight:700;color:#065f46">
        Prefer WhatsApp? We are there.
      </p>
      <p style="margin:0 0 14px;font-size:13px;color:#047857">
        Message us and we will reply the same day.
      </p>
      <a href="https://api.whatsapp.com/send?phone=8801711257498&amp;text=Thank%20you%20for%20choosing%20FoodGet%2C%20your%20complete%20restaurant%20management%20solution."
         style="display:inline-block;padding:11px 20px;background:#16a34a;color:#ffffff;
                text-decoration:none;border-radius:8px;font-weight:700;font-size:14px">
        Chat on WhatsApp
      </a>
    </td>
  </tr>
</table>

<p style="margin:0 0 6px">Best regards,</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px">
  <tr>
    <td style="padding-left:14px;border-left:3px solid #2563eb">
      <p style="margin:0 0 2px;font-size:16px;font-weight:700;color:#2563eb">FoodGet Team</p>
      <p style="margin:0 0 8px;font-size:13px;color:#64748b">{{company_name}} - Business Development</p>
      <p style="margin:0;font-size:13px;line-height:1.8;color:#334155">
        hello@razinsoft.com<br>
        <a href="tel:+8801711257498" style="color:#334155;text-decoration:none">+880 1711 257498</a><br>
        <a href="https://www.razinsoft.com" style="color:#334155">www.razinsoft.com</a>
      </p>
    </td>
  </tr>
</table>

<p style="margin:0;padding-top:14px;border-top:1px solid #e2e8f0;font-size:12px;line-height:1.7;color:#94a3b8">
  You received this because {{business_name}} is listed publicly on Google Maps.
  <a href="{{unsubscribe_url}}" style="color:#94a3b8;text-decoration:underline">Unsubscribe</a>
  and we will not contact this address again.
</p>
HTML;
    }

    /**
     * One outreach template per product line.
     *
     * A restaurant and a pharmacy have nothing in common except that we sell to
     * both, so a single letter for all of them reads as a blast and gets treated
     * as one. Each of these opens on a problem that trade actually recognises,
     * then says what we do about it in one line.
     *
     * Keys follow maps_outreach_<product>. MapsOutreachSetting maps a lead's
     * product to one of these and falls back to maps_lead_outreach.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function mapsOutreach(): array
    {
        return [
            self::make('maps_outreach_restaurant_management', 'Outreach: Restaurant Management', 'Marketing',
                'FoodGet - restaurant management for {{business_name}}',
                'First contact with a restaurant collected from Google Maps. Pitches FoodGet, with a WhatsApp button and a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                self::foodGetBody(),
                footerNote: self::OUTREACH_NOTE),

            self::make('maps_outreach_pos_inventory', 'Outreach: POS & Inventory', 'Marketing',
                'POS software for {{business_name}}',
                'Sent to Maps leads whose category is a prospect for POS & Inventory. Carries a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                '<p>Hello,</p>
                 <p>I came across <strong>{{business_name}}</strong> on Google Maps while looking at
                    {{business_category}} businesses in {{business_city}}.</p>
                 <p>Most shop owners we speak to are still unable to say what is on the shelf without counting it.
                    We build software that handles billing, live stock counts, purchase records and profit per item.</p>
                 <p>Is that costing you time at the moment? If so I can show you what we have
                    built for similar businesses. If not, no reply needed.</p>
                 <p>Best regards,<br>{{company_name}}</p>
                 <p style="margin-top:26px;padding-top:14px;border-top:1px solid #e2e8f0;
                           font-size:12px;color:#94a3b8">
                    You received this because {{business_name}} is listed publicly on Google Maps.
                    <a href="{{unsubscribe_url}}" style="color:#94a3b8">Unsubscribe</a>
                    and we will not contact this address again.
                 </p>',
                footerNote: self::OUTREACH_NOTE),
            self::make('maps_outreach_ecommerce', 'Outreach: eCommerce', 'Marketing',
                'eCommerce software for {{business_name}}',
                'Sent to Maps leads whose category is a prospect for eCommerce. Carries a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                '<p>Hello,</p>
                 <p>I came across <strong>{{business_name}}</strong> on Google Maps while looking at
                    {{business_category}} businesses in {{business_city}}.</p>
                 <p>Most business owners we speak to are still selling through a Facebook page and losing orders in the inbox.
                    We build software that handles an online store with real orders, stock and delivery status in one place.</p>
                 <p>Is that costing you time at the moment? If so I can show you what we have
                    built for similar businesses. If not, no reply needed.</p>
                 <p>Best regards,<br>{{company_name}}</p>
                 <p style="margin-top:26px;padding-top:14px;border-top:1px solid #e2e8f0;
                           font-size:12px;color:#94a3b8">
                    You received this because {{business_name}} is listed publicly on Google Maps.
                    <a href="{{unsubscribe_url}}" style="color:#94a3b8">Unsubscribe</a>
                    and we will not contact this address again.
                 </p>',
                footerNote: self::OUTREACH_NOTE),
            self::make('maps_outreach_pharmacy_management', 'Outreach: Pharmacy Management', 'Marketing',
                'Pharmacy software for {{business_name}}',
                'Sent to Maps leads whose category is a prospect for Pharmacy Management. Carries a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                '<p>Hello,</p>
                 <p>I came across <strong>{{business_name}}</strong> on Google Maps while looking at
                    {{business_category}} businesses in {{business_city}}.</p>
                 <p>Most pharmacy owners we speak to are still tracking expiry dates and batch numbers across several registers.
                    We build software that handles batch and expiry tracking, fast billing, and reorder alerts before a line runs out.</p>
                 <p>Is that costing you time at the moment? If so I can show you what we have
                    built for similar businesses. If not, no reply needed.</p>
                 <p>Best regards,<br>{{company_name}}</p>
                 <p style="margin-top:26px;padding-top:14px;border-top:1px solid #e2e8f0;
                           font-size:12px;color:#94a3b8">
                    You received this because {{business_name}} is listed publicly on Google Maps.
                    <a href="{{unsubscribe_url}}" style="color:#94a3b8">Unsubscribe</a>
                    and we will not contact this address again.
                 </p>',
                footerNote: self::OUTREACH_NOTE),
            self::make('maps_outreach_hospital_management', 'Outreach: Hospital Management', 'Marketing',
                'Hospital software for {{business_name}}',
                'Sent to Maps leads whose category is a prospect for Hospital Management. Carries a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                '<p>Hello,</p>
                 <p>I came across <strong>{{business_name}}</strong> on Google Maps while looking at
                    {{business_category}} businesses in {{business_city}}.</p>
                 <p>Most clinic owners we speak to are still keeping patient files in cabinets and appointments in a diary.
                    We build software that handles patient records, appointments, billing and test reports in one system.</p>
                 <p>Is that costing you time at the moment? If so I can show you what we have
                    built for similar businesses. If not, no reply needed.</p>
                 <p>Best regards,<br>{{company_name}}</p>
                 <p style="margin-top:26px;padding-top:14px;border-top:1px solid #e2e8f0;
                           font-size:12px;color:#94a3b8">
                    You received this because {{business_name}} is listed publicly on Google Maps.
                    <a href="{{unsubscribe_url}}" style="color:#94a3b8">Unsubscribe</a>
                    and we will not contact this address again.
                 </p>',
                footerNote: self::OUTREACH_NOTE),
            self::make('maps_outreach_school_management', 'Outreach: School Management', 'Marketing',
                'School software for {{business_name}}',
                'Sent to Maps leads whose category is a prospect for School Management. Carries a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                '<p>Hello,</p>
                 <p>I came across <strong>{{business_name}}</strong> on Google Maps while looking at
                    {{business_category}} businesses in {{business_city}}.</p>
                 <p>Most school owners we speak to are still keeping attendance, fee collection and result sheets in separate books.
                    We build software that handles admissions, attendance, fees, exams and results, with parents able to see progress.</p>
                 <p>Is that costing you time at the moment? If so I can show you what we have
                    built for similar businesses. If not, no reply needed.</p>
                 <p>Best regards,<br>{{company_name}}</p>
                 <p style="margin-top:26px;padding-top:14px;border-top:1px solid #e2e8f0;
                           font-size:12px;color:#94a3b8">
                    You received this because {{business_name}} is listed publicly on Google Maps.
                    <a href="{{unsubscribe_url}}" style="color:#94a3b8">Unsubscribe</a>
                    and we will not contact this address again.
                 </p>',
                footerNote: self::OUTREACH_NOTE),
            self::make('maps_outreach_university_management', 'Outreach: University Management', 'Marketing',
                'University software for {{business_name}}',
                'Sent to Maps leads whose category is a prospect for University Management. Carries a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                '<p>Hello,</p>
                 <p>I came across <strong>{{business_name}}</strong> on Google Maps while looking at
                    {{business_category}} businesses in {{business_city}}.</p>
                 <p>Most institution owners we speak to are still handling semester registration and results on spreadsheets.
                    We build software that handles admissions, course registration, semester results and fee tracking.</p>
                 <p>Is that costing you time at the moment? If so I can show you what we have
                    built for similar businesses. If not, no reply needed.</p>
                 <p>Best regards,<br>{{company_name}}</p>
                 <p style="margin-top:26px;padding-top:14px;border-top:1px solid #e2e8f0;
                           font-size:12px;color:#94a3b8">
                    You received this because {{business_name}} is listed publicly on Google Maps.
                    <a href="{{unsubscribe_url}}" style="color:#94a3b8">Unsubscribe</a>
                    and we will not contact this address again.
                 </p>',
                footerNote: self::OUTREACH_NOTE),
            self::make('maps_outreach_lms_training', 'Outreach: LMS & Training', 'Marketing',
                'LMS software for {{business_name}}',
                'Sent to Maps leads whose category is a prospect for LMS & Training. Carries a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                '<p>Hello,</p>
                 <p>I came across <strong>{{business_name}}</strong> on Google Maps while looking at
                    {{business_category}} businesses in {{business_city}}.</p>
                 <p>Most centre owners we speak to are still tracking batches, who has paid, and who has fallen behind.
                    We build software that handles online classes, course material, batch tracking and fee records.</p>
                 <p>Is that costing you time at the moment? If so I can show you what we have
                    built for similar businesses. If not, no reply needed.</p>
                 <p>Best regards,<br>{{company_name}}</p>
                 <p style="margin-top:26px;padding-top:14px;border-top:1px solid #e2e8f0;
                           font-size:12px;color:#94a3b8">
                    You received this because {{business_name}} is listed publicly on Google Maps.
                    <a href="{{unsubscribe_url}}" style="color:#94a3b8">Unsubscribe</a>
                    and we will not contact this address again.
                 </p>',
                footerNote: self::OUTREACH_NOTE),
            self::make('maps_outreach_laundry_management', 'Outreach: Laundry Management', 'Marketing',
                'Laundry software for {{business_name}}',
                'Sent to Maps leads whose category is a prospect for Laundry Management. Carries a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                '<p>Hello,</p>
                 <p>I came across <strong>{{business_name}}</strong> on Google Maps while looking at
                    {{business_category}} businesses in {{business_city}}.</p>
                 <p>Most laundry owners we speak to are still matching tickets to garments and remembering what is due when.
                    We build software that handles order tickets, item tracking, pickup and delivery scheduling, and customer accounts.</p>
                 <p>Is that costing you time at the moment? If so I can show you what we have
                    built for similar businesses. If not, no reply needed.</p>
                 <p>Best regards,<br>{{company_name}}</p>
                 <p style="margin-top:26px;padding-top:14px;border-top:1px solid #e2e8f0;
                           font-size:12px;color:#94a3b8">
                    You received this because {{business_name}} is listed publicly on Google Maps.
                    <a href="{{unsubscribe_url}}" style="color:#94a3b8">Unsubscribe</a>
                    and we will not contact this address again.
                 </p>',
                footerNote: self::OUTREACH_NOTE),
            self::make('maps_outreach_ride_sharing', 'Outreach: Ride Sharing', 'Marketing',
                'Ride Sharing software for {{business_name}}',
                'Sent to Maps leads whose category is a prospect for Ride Sharing. Carries a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                '<p>Hello,</p>
                 <p>I came across <strong>{{business_name}}</strong> on Google Maps while looking at
                    {{business_category}} businesses in {{business_city}}.</p>
                 <p>Most service owners we speak to are still coordinating drivers and fares over phone calls.
                    We build software that handles booking, driver assignment, live trip tracking and fare settlement.</p>
                 <p>Is that costing you time at the moment? If so I can show you what we have
                    built for similar businesses. If not, no reply needed.</p>
                 <p>Best regards,<br>{{company_name}}</p>
                 <p style="margin-top:26px;padding-top:14px;border-top:1px solid #e2e8f0;
                           font-size:12px;color:#94a3b8">
                    You received this because {{business_name}} is listed publicly on Google Maps.
                    <a href="{{unsubscribe_url}}" style="color:#94a3b8">Unsubscribe</a>
                    and we will not contact this address again.
                 </p>',
                footerNote: self::OUTREACH_NOTE),
            self::make('maps_outreach_on-demand_services', 'Outreach: On-Demand Services', 'Marketing',
                'On-Demand Services software for {{business_name}}',
                'Sent to Maps leads whose category is a prospect for On-Demand Services. Carries a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                '<p>Hello,</p>
                 <p>I came across <strong>{{business_name}}</strong> on Google Maps while looking at
                    {{business_category}} businesses in {{business_city}}.</p>
                 <p>Most business owners we speak to are still taking jobs by phone and juggling which staff member is free.
                    We build software that handles job booking, staff scheduling, and a record of every customer served.</p>
                 <p>Is that costing you time at the moment? If so I can show you what we have
                    built for similar businesses. If not, no reply needed.</p>
                 <p>Best regards,<br>{{company_name}}</p>
                 <p style="margin-top:26px;padding-top:14px;border-top:1px solid #e2e8f0;
                           font-size:12px;color:#94a3b8">
                    You received this because {{business_name}} is listed publicly on Google Maps.
                    <a href="{{unsubscribe_url}}" style="color:#94a3b8">Unsubscribe</a>
                    and we will not contact this address again.
                 </p>',
                footerNote: self::OUTREACH_NOTE),
            self::make('maps_outreach_garments_management', 'Outreach: Garments Management', 'Marketing',
                'Garments software for {{business_name}}',
                'Sent to Maps leads whose category is a prospect for Garments Management. Carries a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                '<p>Hello,</p>
                 <p>I came across <strong>{{business_name}}</strong> on Google Maps while looking at
                    {{business_category}} businesses in {{business_city}}.</p>
                 <p>Most factory owners we speak to are still following an order through cutting, sewing and finishing on paper.
                    We build software that handles order and production tracking by line, material planning, and delivery schedules.</p>
                 <p>Is that costing you time at the moment? If so I can show you what we have
                    built for similar businesses. If not, no reply needed.</p>
                 <p>Best regards,<br>{{company_name}}</p>
                 <p style="margin-top:26px;padding-top:14px;border-top:1px solid #e2e8f0;
                           font-size:12px;color:#94a3b8">
                    You received this because {{business_name}} is listed publicly on Google Maps.
                    <a href="{{unsubscribe_url}}" style="color:#94a3b8">Unsubscribe</a>
                    and we will not contact this address again.
                 </p>',
                footerNote: self::OUTREACH_NOTE),
            self::make('maps_outreach_erp_manufacturing', 'Outreach: ERP & Manufacturing', 'Marketing',
                'ERP software for {{business_name}}',
                'Sent to Maps leads whose category is a prospect for ERP & Manufacturing. Carries a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                '<p>Hello,</p>
                 <p>I came across <strong>{{business_name}}</strong> on Google Maps while looking at
                    {{business_category}} businesses in {{business_city}}.</p>
                 <p>Most business owners we speak to are still keeping production, stock, purchase and accounts in separate books.
                    We build software that handles one system across production, inventory, purchase, sales and accounts.</p>
                 <p>Is that costing you time at the moment? If so I can show you what we have
                    built for similar businesses. If not, no reply needed.</p>
                 <p>Best regards,<br>{{company_name}}</p>
                 <p style="margin-top:26px;padding-top:14px;border-top:1px solid #e2e8f0;
                           font-size:12px;color:#94a3b8">
                    You received this because {{business_name}} is listed publicly on Google Maps.
                    <a href="{{unsubscribe_url}}" style="color:#94a3b8">Unsubscribe</a>
                    and we will not contact this address again.
                 </p>',
                footerNote: self::OUTREACH_NOTE),
            self::make('maps_outreach_wellness_meditation', 'Outreach: Wellness & Meditation', 'Marketing',
                'Wellness software for {{business_name}}',
                'Sent to Maps leads whose category is a prospect for Wellness & Meditation. Carries a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                '<p>Hello,</p>
                 <p>I came across <strong>{{business_name}}</strong> on Google Maps while looking at
                    {{business_category}} businesses in {{business_city}}.</p>
                 <p>Most studio owners we speak to are still tracking memberships and class bookings in a notebook.
                    We build software that handles memberships, class schedules, bookings and renewal reminders.</p>
                 <p>Is that costing you time at the moment? If so I can show you what we have
                    built for similar businesses. If not, no reply needed.</p>
                 <p>Best regards,<br>{{company_name}}</p>
                 <p style="margin-top:26px;padding-top:14px;border-top:1px solid #e2e8f0;
                           font-size:12px;color:#94a3b8">
                    You received this because {{business_name}} is listed publicly on Google Maps.
                    <a href="{{unsubscribe_url}}" style="color:#94a3b8">Unsubscribe</a>
                    and we will not contact this address again.
                 </p>',
                footerNote: self::OUTREACH_NOTE),
            self::make('maps_outreach_ai_digital_solutions', 'Outreach: AI & Digital Solutions', 'Marketing',
                'AI software for {{business_name}}',
                'Sent to Maps leads whose category is a prospect for AI & Digital Solutions. Carries a required unsubscribe link.',
                'business_name, business_category, business_city, business_country, business_website, business_phone, unsubscribe_url',
                '<p>Hello,</p>
                 <p>I came across <strong>{{business_name}}</strong> on Google Maps while looking at
                    {{business_category}} businesses in {{business_city}}.</p>
                 <p>Most business owners we speak to are still doing manual work that software could handle quietly in the background.
                    We build software that handles custom software and AI automation built around how you already work.</p>
                 <p>Is that costing you time at the moment? If so I can show you what we have
                    built for similar businesses. If not, no reply needed.</p>
                 <p>Best regards,<br>{{company_name}}</p>
                 <p style="margin-top:26px;padding-top:14px;border-top:1px solid #e2e8f0;
                           font-size:12px;color:#94a3b8">
                    You received this because {{business_name}} is listed publicly on Google Maps.
                    <a href="{{unsubscribe_url}}" style="color:#94a3b8">Unsubscribe</a>
                    and we will not contact this address again.
                 </p>',
                footerNote: self::OUTREACH_NOTE),
        ];
    }

    /** A primary button. Inline styles only — every mail client understands this. */
    private static function button(string $url, string $label): string
    {
        return '<p style="margin:24px 0"><a href="'.$url.'" '
            .'style="display:inline-block;padding:12px 22px;background:#4f46e5;color:#ffffff;'
            .'text-decoration:none;border-radius:8px;font-weight:600;font-size:14px">'.$label.'</a></p>';
    }

    /**
     * The welcome mail, which is the one message a customer is most likely to actually read, so it
     * is the one that gets the full treatment: a tinted hero, their account details written back
     * to them, what we do, and one obvious thing to click.
     *
     * Every graphic is a PNG on our own domain (see tools/gen-email-art.mjs) — no mail client
     * renders SVG. Images are also blocked by default in most clients, so nothing here is only an
     * image: the text reads completely on its own with every picture missing.
     */
    private static function welcomeBody(): string
    {
        $img = fn (string $file, int $w, int $h, string $alt, string $extra = '') => '<img src="'
            .asset("images/email/{$file}").'" alt="'.$alt.'" width="'.$w.'" height="'.$h
            .'" style="display:block;width:'.$w.'px;height:'.$h.'px;border:0;'.$extra.'">';

        // Account details, one row each. Written back so a customer can check them at a glance.
        $rows = [
            ['icon-user.png', 'Name', '{{customer_name}}'],
            ['icon-mail.png', 'Email', '{{customer_email}}'],
            ['icon-calendar.png', 'Registration Date', '{{registration_date}}'],
        ];

        // On a phone the value drops under its label — a long email address and its label will
        // not share a 280px line without one of them wrapping mid-word.
        $detailRows = '';
        foreach ($rows as $i => [$icon, $label, $value]) {
            $line = $i < count($rows) - 1 ? 'border-bottom:1px solid #eef2f7' : '';
            $detailRows .= '<tr class="detail-row">'
                .'<td width="34" valign="middle" class="detail-icon" style="padding:13px 0;'.$line.'">'.$img($icon, 20, 20, '').'</td>'
                .'<td valign="middle" class="detail-cell detail-label" style="padding:13px 0;font-size:14px;font-weight:600;color:#0f172a;'.$line.'">'.$label.'</td>'
                .'<td valign="middle" align="right" class="detail-cell detail-value" style="padding:13px 0;font-size:14px;color:#475569;word-break:break-word;'.$line.'">'.$value.'</td>'
                .'</tr>';
        }

        // What we do, two cards side by side. The accent bar under each is the card's own colour.
        $card = fn (string $icon, string $bg, string $accent, string $title, string $text) =>
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
            .' style="background:'.$bg.';border-radius:12px;border-bottom:3px solid '.$accent.'"><tr>'
            .'<td valign="top" width="66" style="padding:18px 0 18px 16px">'.$img($icon, 44, 44, '').'</td>'
            .'<td valign="top" style="padding:18px 16px 18px 0">'
            .'<p style="margin:0 0 5px;font-size:15px;font-weight:700;color:#0f172a">'.$title.'</p>'
            .'<p style="margin:0;font-size:13px;line-height:1.6;color:#475569">'.$text.'</p>'
            .'</td></tr></table>';

        $cardLeft = $card('icon-box.png', '#f2f7ff', '#1a6dff', 'Ready-Made Solutions',
            'Powerful, scalable software you can put to work straight away.');

        $cardRight = $card('icon-code.png', '#f1fbf6', '#10a37f', 'Custom Development',
            'Tailored software built around how your business actually runs.');

        $imgHeroSrc = asset('images/email/hero-welcome.png');
        $imgSupport = $img('icon-support.png', 72, 72, '');
        $imgAvatar = $img('icon-avatar.png', 44, 44, '');

        return <<<HTML
        <!-- hero -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f8ff">
          <tr><td class="pad" style="padding:34px 40px 30px">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
              <td class="stack stack-pad" width="290" valign="top" style="width:290px;padding-right:16px">
                <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:1.4px;color:#1a6dff">WELCOME ABOARD</p>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 14px"><tr>
                  <td style="width:28px;height:3px;background:#1a6dff;font-size:0;line-height:0">&nbsp;</td>
                </tr></table>
                <h1 class="h1" style="margin:0 0 18px;font-size:34px;line-height:1.15;font-weight:800;color:#0f172a">
                  Welcome to<br><span style="color:#1a6dff">{{company_name}}!</span>
                </h1>
                <p style="margin:0 0 10px;font-size:16px;font-weight:700;color:#0f172a">Hi {{customer_name}},</p>
                <p style="margin:0;font-size:14px;line-height:1.7;color:#475569">
                  Thank you for creating your account. We're glad to have you with us —
                  let's build something worth being proud of.
                </p>
              </td>
              <td class="stack hero-art" width="230" valign="middle" style="width:230px">
                <img src="{$imgHeroSrc}" alt="" width="230"
                     style="display:block;width:230px;max-width:100%;height:auto;border:0">
              </td>
            </tr></table>
          </td></tr>
        </table>

        <!-- account details -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr><td class="pad" style="padding:26px 40px 0">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="border:1px solid #e6edf7;border-radius:14px">
              <tr>
                <td valign="top" width="70" class="detail-icon" style="padding:22px 0 22px 20px">{$imgAvatar}</td>
                <td class="detail-head" style="padding:22px 22px 6px 6px">
                  <p style="margin:0 0 10px;font-size:16px;font-weight:700;color:#0f172a">Your Account Details</p>
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    {$detailRows}
                  </table>
                </td>
              </tr>
            </table>
          </td></tr>
        </table>

        <!-- what we do -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr><td class="pad" style="padding:20px 40px 0">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
              <td class="stack stack-pad" width="255" valign="top" style="width:255px;padding-right:10px">{$cardLeft}</td>
              <td class="stack" width="255" valign="top" style="width:255px;padding-left:10px">{$cardRight}</td>
            </tr></table>
          </td></tr>
        </table>

        <!-- the one thing to click -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr><td align="center" class="pad" style="padding:28px 40px 0">
            <a href="{{login_url}}" target="_blank" class="cta"
               style="display:inline-block;padding:15px 40px;background:#1a6dff;color:#ffffff;font-size:16px;
                      font-weight:700;text-align:center;text-decoration:none;border-radius:10px">Go to Dashboard &rarr;</a>
          </td></tr>
        </table>

        <!-- help -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr><td class="pad" style="padding:28px 40px 0">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background:#f4f8ff;border-radius:14px"><tr>
              <td valign="middle" width="96" class="help-art" style="padding:20px 0 20px 20px">{$imgSupport}</td>
              <td valign="middle" class="help-text" style="padding:20px 20px 20px 4px">
                <p style="margin:0 0 4px;font-size:16px;font-weight:700;color:#0f172a">Need help?</p>
                <p style="margin:0 0 10px;font-size:13px;color:#475569">Our support team is always ready to assist you.</p>
                <a href="mailto:{{support_email}}" style="font-size:13px;font-weight:600;color:#1a6dff;text-decoration:none">{{support_email}}</a>
                <span style="color:#cbd5e1"> &nbsp;|&nbsp; </span>
                <a href="{{website_url}}" target="_blank" style="font-size:13px;font-weight:600;color:#1a6dff;text-decoration:none">{{website_url}}</a>
              </td>
            </tr></table>
          </td></tr>
        </table>

        HTML;
    }

    private static function make(string $key, string $name, string $category, string $subject, string $description, string $variables, string $body, bool $fullBleed = false, ?string $footerNote = null): array
    {
        return compact('key', 'name', 'category', 'subject', 'description', 'variables')
            + ['body' => $body, 'full_bleed' => $fullBleed, 'footer_note' => $footerNote];
    }

    /**
     * Wrap a body in the shared shell: the logo, the message, and the footer with the social
     * links and address.
     *
     * Tables rather than divs, and inline styles rather than classes, because Outlook renders the
     * former and strips the latter. The one <style> block only holds the mobile rules, which
     * Outlook ignores anyway — the layout is readable at any width without them.
     *
     * The preheader is the grey line a mail client shows next to the subject. Left empty it picks
     * up whatever text comes first, which usually reads badly.
     */
    public static function wrap(
        string $body,
        string $preheader = '',
        bool $fullBleed = false,
        ?string $footerNote = null,
    ): string {
        // Accurate for account mail, false for anything sent to a stranger —
        // and a false line sits directly under the outreach templates' own
        // disclosure, contradicting it. So each template may supply its own.
        $footerNote ??= 'You are receiving this because you have an account with us.';

        $year = '{{current_year}}';
        $social = self::socialRow();

        // Most bodies are just a run of <p> tags and need the shell to space them off the edges.
        // A body that lays itself out edge to edge — the welcome mail's tinted hero — says so and
        // gets the cell bare.
        $body = $fullBleed
            ? $body
            : '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
                .'<td class="pad" style="padding:30px 40px 8px;font-size:15px;line-height:1.7;color:#334155">'
                .$body
                .'</td></tr></table>';

        return <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <meta name="x-apple-disable-message-reformatting">
        <title>{{company_name}}</title>
        <style>
          @media only screen and (max-width:620px) {
            /* The shell itself: a fixed 600px card would force a sideways scroll on a phone. */
            .shell { width:100% !important; max-width:100% !important; border-radius:12px !important; }
            .outer { padding:14px 8px !important; }

            /* Side-by-side cells become full-width rows. The padding that separated them
               horizontally has to go, or the second column sits indented under the first. */
            .stack { display:block !important; width:100% !important; max-width:100% !important;
                     padding-left:0 !important; padding-right:0 !important; }
            .stack-pad { padding-bottom:18px !important; }

            .pad { padding-left:20px !important; padding-right:20px !important; }
            .h1 { font-size:27px !important; }
            .hero-art { text-align:center !important; }
            .hero-art img { margin-left:auto !important; margin-right:auto !important; }

            /* A label and its value will not both fit on one narrow line, so the value drops
               under the label and both sit left-aligned. */
            .detail-label { display:block !important; }
            .detail-value { display:block !important; width:auto !important; text-align:left !important;
                            padding:0 0 12px !important; }
            .detail-row td { border-bottom:0 !important; }
            .detail-cell { display:block !important; width:auto !important; padding:12px 0 2px !important; }
            /* The decorative icons go: at 320px the space they take is space an email address
               needs, and it is the address that has to stay readable. */
            .detail-icon { display:none !important; }
            .detail-head { display:block !important; padding:20px 20px 4px !important; }

            /* The support panel reads better with the headset above the words than beside them. */
            /* width:auto, not 100% — a full-width block plus its own side padding overflows
               the panel, and a mail client will not do box-sizing for us. */
            .help-art { display:block !important; width:auto !important; text-align:left !important;
                        padding:18px 20px 0 !important; }
            .help-text { display:block !important; width:auto !important; padding:10px 20px 18px !important; }

            .cta { display:block !important; }
          }
        </style>
        </head>
        <body style="margin:0;padding:0;background:#eef2f8;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#1e293b;-webkit-font-smoothing:antialiased">
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all">{$preheader}</div>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2f8">
        <tr><td align="center" class="outer" style="padding:28px 12px">

          <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" class="shell"
                 style="width:600px;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden">

            <!-- message -->
            <tr><td style="padding:0">
        {$body}
            </td></tr>

            <!-- footer -->
            <tr><td class="pad" style="padding:0 40px 34px">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr><td align="center" style="padding:26px 0 18px;border-top:1px solid #eef2f7">
                  <p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#0f172a">Thank you for choosing {{company_name}}.</p>
                  <p style="margin:0;font-size:13px;color:#64748b">We look forward to helping you succeed.</p>
                </td></tr>
                <tr><td align="center" style="padding:6px 0 14px">
                  <img src="{{company_logo}}" alt="{{company_name}}" width="140"
                       style="display:block;width:140px;max-width:55%;height:auto;border:0;margin:0 auto">
                </td></tr>
                <tr><td align="center" style="padding:0 0 18px">{$social}</td></tr>
                <tr><td align="center" style="padding-top:16px;border-top:1px solid #eef2f7">
                  <p style="margin:0 0 4px;font-size:12px;color:#94a3b8">&copy; {$year} {{company_name}}. All rights reserved.</p>
                  <p style="margin:0 0 4px;font-size:12px;color:#94a3b8">{{company_address}}</p>
                  <p style="margin:0;font-size:12px;color:#94a3b8">{$footerNote}</p>
                </td></tr>
              </table>
            </td></tr>

          </table>

        </td></tr>
        </table>
        </body>
        </html>
        HTML;
    }

    /**
     * The row of social buttons, built from config/brand.php so it matches the public website.
     *
     * Laid out as table cells rather than inline-blocks: Outlook collapses the gaps between
     * inline-blocks and the icons end up touching.
     */
    private static function socialRow(): string
    {
        $cells = '';

        foreach (config('brand.social', []) as $key => $network) {
            $icon = asset("images/email/social/{$key}.png");
            $cells .= '<td style="padding:0 6px">'
                .'<a href="'.e($network['url']).'" target="_blank" style="text-decoration:none">'
                .'<img src="'.e($icon).'" alt="'.e($network['label']).'" width="34" height="34" '
                .'style="display:block;width:34px;height:34px;border:0;border-radius:17px">'
                .'</a></td>';
        }

        return $cells === ''
            ? ''
            : '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto"><tr>'.$cells.'</tr></table>';
    }
}
