<?php

namespace Tests\Feature;

use App\Jobs\SendQueuedEmail;
use App\Models\EmailConfig;
use App\Models\EmailLog;
use App\Models\EmailNotificationRule;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Services\Email\EmailAnalytics;
use App\Services\Email\EmailBodyBuilder;
use App\Services\Email\EmailDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The rules that protect the sending domain.
 *
 * These cover the decisions that are expensive to get wrong — mailing a suppressed address,
 * sending the same message twice, ignoring a switched-off notification — rather than asserting
 * that Eloquent saves rows.
 */
class EmailManagementTest extends TestCase
{
    use RefreshDatabase;

    private function config(array $attributes = []): EmailConfig
    {
        return EmailConfig::create($attributes + [
            'name' => 'Test SMTP',
            'provider' => 'custom',
            'host' => 'localhost',
            'port' => 587,
            'from_email' => 'test@example.com',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    private function dispatcher(): EmailDispatcher
    {
        return new EmailDispatcher();
    }

    // ---------------------------------------------------------------- sending rules

    public function test_it_queues_a_message_rather_than_sending_inline(): void
    {
        Queue::fake();
        $this->config();

        $log = $this->dispatcher()->send('someone@example.com', 'Hello', '<p>Hi</p>');

        $this->assertNotNull($log);
        $this->assertSame('pending', $log->status);
        Queue::assertPushed(SendQueuedEmail::class);
    }

    public function test_it_refuses_a_suppressed_address(): void
    {
        Queue::fake();
        $this->config();
        EmailSuppression::add('bounced@example.com', 'bounce');

        $this->assertNull($this->dispatcher()->send('bounced@example.com', 'Hello', '<p>Hi</p>'));
        $this->assertSame(0, EmailLog::count());
    }

    public function test_it_refuses_an_invalid_address(): void
    {
        Queue::fake();
        $this->config();

        $this->assertNull($this->dispatcher()->send('not-an-address', 'Hello', '<p>Hi</p>'));
    }

    public function test_it_refuses_a_duplicate_still_in_flight(): void
    {
        Queue::fake();
        $this->config();

        $first = $this->dispatcher()->send('someone@example.com', 'Hello', '<p>Hi</p>');
        $second = $this->dispatcher()->send('someone@example.com', 'Hello', '<p>Hi</p>');

        $this->assertNotNull($first);
        $this->assertNull($second, 'The same message should not be queued twice.');
    }

    public function test_a_deliberate_resend_is_allowed_through(): void
    {
        Queue::fake();
        $this->config();

        $this->dispatcher()->send('someone@example.com', 'Hello', '<p>Hi</p>');
        $again = $this->dispatcher()->send('someone@example.com', 'Hello', '<p>Hi</p>', null, ['dedupe' => false]);

        $this->assertNotNull($again);
    }

    public function test_nothing_is_queued_without_an_active_smtp_account(): void
    {
        Queue::fake();

        $this->assertNull($this->dispatcher()->send('someone@example.com', 'Hello', '<p>Hi</p>'));
    }

    // ---------------------------------------------------------------- the worker

    public function test_the_worker_skips_a_message_cancelled_while_queued(): void
    {
        $config = $this->config();

        $log = EmailLog::create([
            'email_config_id' => $config->id,
            'to_email' => 'someone@example.com',
            'subject' => 'Hello',
            'body_html' => '<p>Hi</p>',
            'status' => 'cancelled',
        ]);

        (new SendQueuedEmail($log->id))->handle();

        $this->assertSame('cancelled', $log->fresh()->status);
    }

    public function test_the_worker_stops_if_the_address_was_suppressed_after_queueing(): void
    {
        $config = $this->config();

        $log = EmailLog::create([
            'email_config_id' => $config->id,
            'to_email' => 'late@example.com',
            'subject' => 'Hello',
            'body_html' => '<p>Hi</p>',
            'status' => 'pending',
        ]);

        // The bounce arrives while the message sits in the queue.
        EmailSuppression::add('late@example.com', 'bounce');

        (new SendQueuedEmail($log->id))->handle();

        $this->assertSame('cancelled', $log->fresh()->status);
    }

    // ---------------------------------------------------------------- notification rules

    public function test_a_switched_off_notification_does_not_send(): void
    {
        Queue::fake();
        $this->config();

        EmailTemplate::create(['key' => 'thing', 'name' => 'Thing', 'subject' => 'Hi', 'body' => '<p>Hi</p>', 'is_active' => true]);
        EmailNotificationRule::create(['key' => 'thing.happened', 'name' => 'Thing', 'is_enabled' => false]);

        $this->assertNull($this->dispatcher()->sendTemplate('thing', 'someone@example.com', [], ['event' => 'thing.happened']));
    }

    public function test_an_event_with_no_rule_is_allowed(): void
    {
        Queue::fake();
        $this->config();
        EmailTemplate::create(['key' => 'thing', 'name' => 'Thing', 'subject' => 'Hi', 'body' => '<p>Hi</p>', 'is_active' => true]);

        $this->assertNotNull(
            $this->dispatcher()->sendTemplate('thing', 'someone@example.com', [], ['event' => 'never.configured']),
            'A notification added in code should work before anyone creates a rule for it.',
        );
    }

    public function test_an_inactive_template_does_not_send(): void
    {
        Queue::fake();
        $this->config();
        EmailTemplate::create(['key' => 'off', 'name' => 'Off', 'subject' => 'Hi', 'body' => '<p>Hi</p>', 'is_active' => false]);

        $this->assertNull($this->dispatcher()->sendTemplate('off', 'someone@example.com'));
    }

    // ---------------------------------------------------------------- body building

    public function test_the_plain_text_part_keeps_link_targets(): void
    {
        $text = EmailBodyBuilder::toPlainText('<p>Please <a href="https://example.com/pay">pay now</a>.</p>');

        $this->assertStringContainsString('pay now (https://example.com/pay)', $text);
    }

    public function test_the_plain_text_part_drops_hidden_preheaders_and_head(): void
    {
        $html = '<html><head><title>Brand</title></head><body>'
            .'<div style="display:none">Hidden preheader</div><p>Real content</p></body></html>';

        $text = EmailBodyBuilder::toPlainText($html);

        $this->assertStringNotContainsString('Hidden preheader', $text);
        $this->assertStringNotContainsString('Brand', $text);
        $this->assertStringContainsString('Real content', $text);
    }

    public function test_tracking_adds_a_pixel_and_rewrites_links(): void
    {
        $config = $this->config();
        $log = EmailLog::create([
            'email_config_id' => $config->id,
            'to_email' => 'a@example.com',
            'subject' => 'Hi',
            'body_html' => '<p><a href="https://example.com/go">Go</a></p>',
            'status' => 'pending',
        ]);

        $html = EmailBodyBuilder::withTracking($log->body_html, $log);

        $this->assertStringContainsString('email/track/open/'.$log->tracking_id, $html);
        $this->assertStringContainsString('email/track/click/'.$log->tracking_id, $html);
    }

    public function test_tracking_leaves_mailto_and_anchor_links_alone(): void
    {
        $config = $this->config();
        $log = EmailLog::create([
            'email_config_id' => $config->id,
            'to_email' => 'a@example.com',
            'subject' => 'Hi',
            'body_html' => '<a href="mailto:x@y.com">Mail</a><a href="#top">Top</a>',
            'status' => 'pending',
        ]);

        $html = EmailBodyBuilder::withTracking($log->body_html, $log);

        $this->assertStringContainsString('href="mailto:x@y.com"', $html);
        $this->assertStringContainsString('href="#top"', $html);
    }

    // ---------------------------------------------------------------- tracking endpoints

    public function test_the_open_pixel_records_an_open_and_returns_an_image(): void
    {
        $config = $this->config();
        $log = EmailLog::create([
            'email_config_id' => $config->id,
            'to_email' => 'a@example.com', 'subject' => 'Hi', 'body_html' => '<p>Hi</p>', 'status' => 'sent',
        ]);

        $this->get(route('email.track.open', $log->tracking_id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');

        $this->assertSame(1, $log->fresh()->open_count);
    }

    public function test_an_unknown_tracking_id_still_returns_a_pixel(): void
    {
        // Never reveal which ids exist.
        $this->get(route('email.track.open', '00000000-0000-0000-0000-000000000000'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');
    }

    public function test_the_click_endpoint_refuses_to_redirect_off_http(): void
    {
        $config = $this->config();
        $log = EmailLog::create([
            'email_config_id' => $config->id,
            'to_email' => 'a@example.com', 'subject' => 'Hi', 'body_html' => '<p>Hi</p>', 'status' => 'sent',
        ]);

        // An open redirect here would make our own domain a phishing tool.
        $this->get(route('email.track.click', ['tracking' => $log->tracking_id, 'url' => 'javascript:alert(1)']))
            ->assertRedirect(config('app.url'));
    }

    public function test_unsubscribing_suppresses_the_address(): void
    {
        $config = $this->config();
        $log = EmailLog::create([
            'email_config_id' => $config->id,
            'to_email' => 'leaver@example.com', 'subject' => 'Hi', 'body_html' => '<p>Hi</p>', 'status' => 'sent',
        ]);

        $this->get(route('email.unsubscribe', $log->tracking_id))->assertOk();

        $this->assertTrue(EmailSuppression::has('leaver@example.com'));
    }

    // ---------------------------------------------------------------- webhook

    public function test_the_webhook_is_closed_without_the_secret(): void
    {
        config(['services.email_webhook.secret' => 'right-secret']);

        $this->postJson(route('email.webhook'), ['event' => 'bounce', 'email' => 'a@example.com'])
            ->assertStatus(401);
    }

    public function test_a_hard_bounce_suppresses_but_a_soft_bounce_does_not(): void
    {
        config(['services.email_webhook.secret' => 's3cret']);
        $headers = ['X-Webhook-Secret' => 's3cret'];

        $this->postJson(route('email.webhook'), [
            'RecordType' => 'Bounce', 'Type' => 'HardBounce', 'Email' => 'hard@example.com',
        ], $headers)->assertOk();

        $this->postJson(route('email.webhook'), [
            'RecordType' => 'Bounce', 'Type' => 'SoftBounce', 'Email' => 'soft@example.com',
        ], $headers)->assertOk();

        $this->assertTrue(EmailSuppression::has('hard@example.com'));
        $this->assertFalse(
            EmailSuppression::has('soft@example.com'),
            'A full mailbox is temporary — suppressing it would lose a real customer.',
        );
    }

    public function test_a_spam_complaint_always_suppresses(): void
    {
        config(['services.email_webhook.secret' => 's3cret']);

        $this->postJson(route('email.webhook'), [
            'event-data' => ['event' => 'complained', 'recipient' => 'angry@example.com'],
        ], ['X-Webhook-Secret' => 's3cret'])->assertOk();

        $this->assertTrue(EmailSuppression::has('angry@example.com'));
    }

    // ---------------------------------------------------------------- analytics

    public function test_rates_are_measured_against_delivered_not_everything_queued(): void
    {
        $config = $this->config();

        // 10 sent, 2 of them bounced, 4 opened → 4/8 delivered = 50%, not 4/10.
        foreach (range(1, 10) as $i) {
            EmailLog::create([
                'email_config_id' => $config->id,
                'to_email' => "u{$i}@example.com", 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
                'status' => 'sent', 'sent_at' => now(),
                'bounced' => $i <= 2,
                'first_opened_at' => $i > 2 && $i <= 6 ? now() : null,
            ]);
        }

        $summary = (new EmailAnalytics())->summary();

        $this->assertSame(8, $summary['delivered']);
        $this->assertSame(50.0, $summary['open_rate']);
    }

    public function test_rates_are_null_rather_than_zero_when_nothing_was_sent(): void
    {
        // "0% opened" and "nothing sent yet" are very different things.
        $this->assertNull((new EmailAnalytics())->summary()['open_rate']);
    }

    // ---------------------------------------------------------------- templates

    public function test_unfilled_variables_are_removed_rather_than_shown(): void
    {
        $template = EmailTemplate::create([
            'key' => 'x', 'name' => 'X', 'subject' => 'Hi {{customer_name}}',
            'body' => '<p>Owed {{amount}}</p>', 'is_active' => true,
        ]);

        $out = $template->renderFor(['customer_name' => 'Rahim']);

        $this->assertSame('Hi Rahim', $out['subject']);
        $this->assertStringNotContainsString('{{', $out['html'], 'A customer must never see raw braces.');
    }

    public function test_a_config_reports_when_it_is_over_its_limits(): void
    {
        $config = $this->config(['hourly_limit' => 2]);

        foreach (range(1, 2) as $i) {
            EmailLog::create([
                'email_config_id' => $config->id,
                'to_email' => "u{$i}@example.com", 'subject' => 'Hi', 'body_html' => '<p>Hi</p>',
                'status' => 'sent', 'sent_at' => now(),
            ]);
        }

        $this->assertFalse($config->withinLimits());
    }

    public function test_the_shell_carries_the_brand_and_the_websites_social_links(): void
    {
        $html = \App\Services\Email\DefaultTemplates::wrap('<p>Hello</p>');

        $this->assertStringContainsString('{{company_logo}}', $html, 'Every email is signed with the logo.');
        $this->assertStringContainsString('{{company_address}}', $html);

        foreach (config('brand.social') as $key => $network) {
            $this->assertStringContainsString($network['url'], $html, "The {$key} link is missing from the footer.");
            $this->assertStringContainsString("images/email/social/{$key}.png", $html);
        }
    }

    public function test_the_logo_falls_back_to_the_bundled_mark_when_none_is_uploaded(): void
    {
        \App\Models\InvoiceSetting::query()->delete();

        $this->assertStringContainsString('razinsoft-logo.png', EmailTemplate::globalValues()['company_logo']);
    }

    public function test_the_welcome_email_writes_the_account_details_back(): void
    {
        $this->artisan('email:seed-templates', ['--force' => true]);

        $out = EmailTemplate::where('key', 'welcome_client')->firstOrFail()->renderFor([
            'customer_name' => 'Rahim Uddin',
            'customer_email' => 'rahim@example.com',
            'registration_date' => '26 May 2025',
        ]);

        foreach (['Rahim Uddin', 'rahim@example.com', '26 May 2025'] as $expected) {
            $this->assertStringContainsString($expected, $out['html']);
        }

        // Images are blocked by default in most clients, so the message has to survive without them.
        $this->assertStringContainsString('Your Account Details', $out['text']);
        $this->assertStringContainsString('Rahim Uddin', $out['text']);
    }

    public function test_the_plain_text_part_keeps_table_cells_apart(): void
    {
        $text = \App\Services\Email\EmailBodyBuilder::toPlainText('<table><tr><td>Name</td><td>Rahim</td></tr></table>');

        $this->assertStringContainsString('Name Rahim', $text, 'Cells must not run together.');
    }

    public function test_the_shell_can_stack_its_columns_on_a_phone(): void
    {
        $html = \App\Services\Email\DefaultTemplates::wrap('<p>Hello</p>');

        $this->assertStringContainsString('@media only screen and (max-width:620px)', $html);
        // A fixed 600px card is what makes a phone scroll sideways.
        $this->assertStringContainsString('.shell { width:100% !important', $html);
        $this->assertStringContainsString('.stack { display:block !important', $html);
    }

    public function test_the_login_link_points_at_the_customer_site_not_the_staff_panel(): void
    {
        config(['app.url' => 'https://deskadmin.example.com']);

        $login = EmailTemplate::globalValues()['login_url'];

        $this->assertSame(config('brand.login_url'), $login);
        $this->assertStringNotContainsString('deskadmin', $login, 'Customers cannot sign in to the staff panel.');
    }

    // ---------------------------------------------------------------- welcome on first sign-in

    public function test_a_customer_is_welcomed_the_first_time_they_sign_in_and_never_again(): void
    {
        $this->artisan('email:seed-templates');
        $this->config();

        $user = \App\Models\User::create([
            'name' => 'Rahim Uddin', 'email' => 'rahim@example.com',
            'password' => bcrypt('secret123'), 'role' => 'customer', 'status' => 'active',
        ]);
        $user->forceFill(['welcomed_at' => null])->saveQuietly();

        $credentials = ['email' => 'rahim@example.com', 'password' => 'secret123'];

        $this->postJson('/api/auth/login', $credentials)->assertOk();
        $this->assertSame(1, EmailLog::where('to_email', 'rahim@example.com')->count());
        $this->assertNotNull($user->fresh()->welcomed_at);

        // Signing in again must not send a second one.
        $this->postJson('/api/auth/login', $credentials)->assertOk();
        $this->assertSame(1, EmailLog::where('to_email', 'rahim@example.com')->count());
    }

    public function test_turning_the_welcome_rule_off_stops_it_without_burning_the_one_chance(): void
    {
        $this->artisan('email:seed-templates');
        $this->artisan('email:seed-rules');
        $this->config();

        // Saved through the model, not the query builder — the cache is cleared by a model event.
        \App\Models\EmailNotificationRule::where('key', 'account.welcome')->firstOrFail()
            ->forceFill(['is_enabled' => false])->save();

        $user = \App\Models\User::create([
            'name' => 'Karim', 'email' => 'karim@example.com',
            'password' => bcrypt('secret123'), 'role' => 'customer', 'status' => 'active',
        ]);
        $user->forceFill(['welcomed_at' => null])->saveQuietly();

        $this->postJson('/api/auth/login', ['email' => 'karim@example.com', 'password' => 'secret123'])->assertOk();

        $this->assertSame(0, EmailLog::where('to_email', 'karim@example.com')->count());
        // Not stamped — switching the rule back on must still reach them.
        $this->assertNull($user->fresh()->welcomed_at);
    }

    public function test_existing_customers_are_not_welcomed_retrospectively(): void
    {
        $user = \App\Models\User::create([
            'name' => 'Old Client', 'email' => 'old@example.com',
            'password' => bcrypt('secret123'), 'role' => 'customer', 'status' => 'active',
        ]);

        // The migration stamps every account that already exists; a new row gets the same
        // treatment only because it was created after the column. Guard the intent instead:
        $user->forceFill(['welcomed_at' => now()->subYear()])->saveQuietly();

        $this->postJson('/api/auth/login', ['email' => 'old@example.com', 'password' => 'secret123'])->assertOk();

        $this->assertSame(0, EmailLog::where('to_email', 'old@example.com')->count());
    }

    public function test_the_old_smtp_account_is_carried_into_the_new_module(): void
    {
        \App\Models\EmailSetting::create([
            'mailer' => 'smtp', 'host' => 'mail.example.com', 'port' => 587, 'encryption' => 'tls',
            'username' => 'teams@example.com', 'password' => 'plaintext-secret',
            'from_address' => 'teams@example.com', 'from_name' => 'Example', 'is_enabled' => true,
        ]);

        $this->artisan('email:import-legacy-smtp')->assertSuccessful();

        $config = \App\Models\EmailConfig::firstOrFail();

        $this->assertSame('mail.example.com', $config->host);
        // Decrypted and re-encrypted, not copied as ciphertext.
        $this->assertSame('plaintext-secret', $config->password);
        // Nothing sends until something is both default and active.
        $this->assertTrue($config->is_default);
        $this->assertTrue($config->is_active);
        $this->assertNotNull(\App\Models\EmailConfig::pick(null));

        // Running it twice must not create a second copy.
        $this->artisan('email:import-legacy-smtp')->assertSuccessful();
        $this->assertSame(1, \App\Models\EmailConfig::count());
    }

    // ---------------------------------------------------------------- everything goes through the module

    public function test_a_password_reset_is_queued_through_the_module(): void
    {
        $this->artisan('email:seed-templates');
        $this->config();

        $user = \App\Models\User::create([
            'name' => 'Rahim', 'email' => 'reset@example.com',
            'password' => bcrypt('secret123'), 'role' => 'customer', 'status' => 'active',
        ]);

        $user->sendPasswordResetNotification('tok-123');

        $log = EmailLog::where('to_email', 'reset@example.com')->firstOrFail();

        $this->assertStringContainsString('tok-123', $log->body_html, 'The reset token must survive.');
        $this->assertStringContainsString('reset-password', $log->body_html);
    }

    public function test_an_invoice_is_queued_with_its_pdf_attached(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->artisan('email:seed-templates');
        $this->config();

        $invoice = $this->invoice();

        $log = app(\App\Services\Email\InvoiceMailer::class)->send($invoice);

        $this->assertNotNull($log);
        $this->assertSame(1, $log->attachments()->count(), 'The invoice PDF has to travel with it.');
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($log->attachments()->first()->path);

        // A reminder is a deliberate repeat, so the duplicate guard must not swallow it.
        $reminder = app(\App\Services\Email\InvoiceMailer::class)->remind($invoice);
        $this->assertNotNull($reminder);
        $this->assertNotSame($log->subject, $reminder->subject);
    }

    public function test_the_default_account_becomes_laravels_own_mailer(): void
    {
        config(['mail.default' => 'log']);

        $config = $this->config();
        $config->makeDefaultMailer();

        $this->assertSame('db-'.$config->id, config('mail.default'));
        $this->assertSame($config->host, config('mail.mailers.db-'.$config->id.'.host'));
    }

    private function invoice(): \App\Models\ClientInvoice
    {
        return \App\Models\ClientInvoice::create([
            'invoice_number' => 'INV-TEST-1',
            'bill_to_name' => 'Test Client',
            'bill_to_email' => 'billing@example.com',
            'currency' => 'USD',
            'total' => 100,
            'status' => 'sent',
            'invoice_date' => now(),
            'due_date' => now()->addDays(7),
        ]);
    }
}
