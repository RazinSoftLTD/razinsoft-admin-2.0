<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * A switch per event: does this system email when an invoice is sent, a ticket is answered, and
 * so on. Turning one off stops that email without touching code or disabling the template — the
 * template may be shared, the rule is about the event.
 *
 * The lookup is cached because it runs on paths that send mail; any write clears it.
 */
class EmailNotificationRule extends Model
{
    private const CACHE_KEY = 'email.notification-rules';

    protected $guarded = [];

    protected $casts = ['is_enabled' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    /**
     * Whether this event may send.
     *
     * An event with no rule row is allowed: a new notification added in code should work
     * immediately rather than fail silently until someone remembers to create a rule.
     */
    public static function allows(string $key): bool
    {
        $rules = Cache::remember(self::CACHE_KEY, now()->addHour(),
            fn () => static::pluck('is_enabled', 'key')->all());

        return (bool) ($rules[$key] ?? true);
    }

    /** The template this event should use, when the rule names one. */
    public static function templateKeyFor(string $key): ?string
    {
        return static::where('key', $key)->with('template:id,key')->first()?->template?->key;
    }

    /** The events the system ships with, in the order they are shown. */
    public static function defaults(): array
    {
        return [
            ['key' => 'account.welcome', 'name' => 'Welcome email', 'group' => 'Account', 'template' => 'welcome_client', 'description' => 'When a client account is created.'],
            ['key' => 'account.verification', 'name' => 'Email verification', 'group' => 'Account', 'template' => 'email_verification', 'description' => 'When an address needs confirming.'],
            ['key' => 'account.password_reset', 'name' => 'Password reset', 'group' => 'Account', 'template' => 'password_reset', 'description' => 'When someone asks to reset their password.'],

            ['key' => 'invoice.sent', 'name' => 'Invoice issued', 'group' => 'Billing', 'template' => 'invoice_sent', 'description' => 'When an invoice is sent to a client.'],
            ['key' => 'payment.received', 'name' => 'Payment received', 'group' => 'Billing', 'template' => 'payment_received', 'description' => 'When a payment is recorded.'],
            ['key' => 'payment.failed', 'name' => 'Payment failed', 'group' => 'Billing', 'template' => 'payment_failed', 'description' => 'When an online payment attempt fails.'],
            ['key' => 'payment.refunded', 'name' => 'Refund processed', 'group' => 'Billing', 'template' => 'refund_processed', 'description' => 'When a refund is issued.'],
            ['key' => 'order.confirmed', 'name' => 'Order confirmation', 'group' => 'Billing', 'template' => 'order_confirmation', 'description' => 'When an order is placed.'],
            ['key' => 'license.delivered', 'name' => 'License delivered', 'group' => 'Billing', 'template' => 'license_delivered', 'description' => 'When a purchased license is issued.'],
            ['key' => 'subscription.activated', 'name' => 'Subscription activated', 'group' => 'Billing', 'template' => 'subscription_activated', 'description' => 'When a subscription starts or renews.'],
            ['key' => 'subscription.expired', 'name' => 'Subscription expired', 'group' => 'Billing', 'template' => 'subscription_expired', 'description' => 'When a subscription lapses.'],
            ['key' => 'subscription.trial_ending', 'name' => 'Trial ending', 'group' => 'Billing', 'template' => 'trial_ending', 'description' => 'A few days before a trial runs out.'],

            ['key' => 'ticket.created', 'name' => 'Ticket opened', 'group' => 'Support', 'template' => 'ticket_created', 'description' => 'When a support ticket is opened.'],
            ['key' => 'ticket.replied', 'name' => 'Ticket reply', 'group' => 'Support', 'template' => 'ticket_reply', 'description' => 'When an agent replies.'],
            ['key' => 'ticket.closed', 'name' => 'Ticket closed', 'group' => 'Support', 'template' => 'ticket_closed', 'description' => 'When a ticket is resolved.'],

            ['key' => 'project.created', 'name' => 'Project started', 'group' => 'Projects', 'template' => 'project_created', 'description' => 'When a project is created for a client.'],
            ['key' => 'project.updated', 'name' => 'Project update', 'group' => 'Projects', 'template' => 'project_updated', 'description' => 'When a milestone or status changes.'],
            ['key' => 'project.completed', 'name' => 'Project completed', 'group' => 'Projects', 'template' => 'project_completed', 'description' => 'When a project is marked complete.'],
            ['key' => 'meeting.booked', 'name' => 'Meeting invitation', 'group' => 'Projects', 'template' => 'meeting_booked', 'description' => 'When a meeting is booked.'],

            ['key' => 'marketing.campaign', 'name' => 'Marketing campaign', 'group' => 'Marketing', 'template' => 'marketing_campaign', 'description' => 'One-off campaigns sent from the panel.'],
            ['key' => 'marketing.newsletter', 'name' => 'Newsletter', 'group' => 'Marketing', 'template' => 'newsletter', 'description' => 'Newsletters sent from the panel.'],
            ['key' => 'system.maintenance', 'name' => 'Maintenance notice', 'group' => 'System', 'template' => 'maintenance_notice', 'description' => 'Before planned downtime.'],
        ];
    }
}
