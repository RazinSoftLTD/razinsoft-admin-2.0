<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email Management — the tables the module is built on.
 *
 * The panel already had a single-row `email_settings` and a small `email_templates`. Those stay
 * put: this adds the multi-account, logged, tracked system alongside, and the first SMTP account
 * is seeded from the old settings row so mail keeps working the moment this lands.
 *
 * Sending itself goes through Laravel's queue (`jobs`), so there is no second queue table — an
 * `email_logs` row IS the queue entry, moving pending → sending → sent/failed. One table means the
 * log and the queue can never disagree about what happened to a message.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- SMTP accounts -------------------------------------------------
        Schema::create('email_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                  // "Gmail — support@"
            $table->string('provider', 40)->default('custom');       // gmail | ses | mailgun | …
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('username')->nullable();
            $table->text('password')->nullable();                    // encrypted cast
            $table->string('encryption', 10)->nullable();            // tls | ssl | null
            $table->string('from_name')->nullable();
            $table->string('from_email');
            $table->string('reply_to')->nullable();
            $table->string('return_path')->nullable();               // bounces come back here
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(10);   // lower runs first
            // Provider limits, so the queue can throttle instead of getting the account blocked.
            $table->unsignedInteger('hourly_limit')->nullable();
            $table->unsignedInteger('daily_limit')->nullable();
            // Health, written by the connection test and by real sends.
            $table->string('health', 20)->default('unknown');        // unknown | ok | failing
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'priority']);
        });

        // ---- every message, queued or sent ---------------------------------
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('tracking_id')->unique();                   // pixel + click links
            $table->foreignId('email_config_id')->nullable()->constrained('email_configs')->nullOnDelete();
            $table->foreignId('email_template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->string('module', 60)->nullable();                // invoices | tickets | meetings …
            $table->nullableMorphs('related');                       // the invoice/ticket it came from
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();  // recipient, when known
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->string('subject');
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->string('status', 20)->default('pending');        // pending|sending|sent|failed|cancelled
            $table->unsignedSmallInteger('priority')->default(10);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('scheduled_at')->nullable();           // delayed / scheduled sends
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('first_clicked_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->unsignedInteger('click_count')->default(0);
            $table->boolean('bounced')->default(false);
            $table->boolean('complained')->default(false);
            // Stops the same message going out twice — see EmailDispatcher::fingerprint().
            $table->string('fingerprint', 64)->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
            $table->index(['to_email', 'created_at']);
            $table->index('module');
        });

        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_log_id')->constrained('email_logs')->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
        });

        // ---- tracking ------------------------------------------------------
        Schema::create('email_opens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_log_id')->constrained('email_logs')->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('opened_at');
            $table->index(['email_log_id', 'opened_at']);
        });

        Schema::create('email_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_log_id')->constrained('email_logs')->cascadeOnDelete();
            $table->text('url');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('clicked_at');
            $table->index(['email_log_id', 'clicked_at']);
        });

        Schema::create('email_bounces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_log_id')->nullable()->constrained('email_logs')->nullOnDelete();
            $table->string('email');
            $table->string('type', 20)->default('hard');             // hard | soft
            $table->text('reason')->nullable();
            $table->json('payload')->nullable();                     // raw provider webhook
            $table->timestamps();
            $table->index('email');
        });

        Schema::create('email_spam_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_log_id')->nullable()->constrained('email_logs')->nullOnDelete();
            $table->string('email');
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index('email');
        });

        /**
         * Addresses we must not mail again: hard bounces, complaints, unsubscribes. Checked
         * before every send, which is the single biggest thing protecting sender reputation.
         */
        Schema::create('email_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('reason', 30);                            // bounce | complaint | unsubscribe | manual
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // ---- which events are allowed to email at all -----------------------
        Schema::create('email_notification_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                          // invoice.sent, ticket.created …
            $table->string('name');
            $table->string('group', 40)->default('General');
            $table->text('description')->nullable();
            $table->foreignId('email_template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        // ---- manual sends / campaigns --------------------------------------
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->foreignId('email_template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->foreignId('email_config_id')->nullable()->constrained('email_configs')->nullOnDelete();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->json('audience')->nullable();                      // the filter that picked recipients
            $table->string('status', 20)->default('draft');            // draft|scheduled|sending|sent|cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('email_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained('email_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('email_log_id')->nullable()->constrained('email_logs')->nullOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['email_campaign_id', 'email']);
        });

        // ---- the existing templates table grows up --------------------------
        Schema::table('email_templates', function (Blueprint $table) {
            $table->string('category', 40)->default('System')->after('name');
            $table->longText('body_text')->nullable()->after('body');   // the plain-text half
            $table->text('description')->nullable()->after('body_text');
            $table->boolean('is_system')->default(false)->after('is_active'); // seeded ones can't be deleted
        });

        $this->seedFirstConfig();
    }

    /**
     * Carry the old single-row SMTP settings over as the first account, so nothing that already
     * sends mail stops working when this migration runs.
     */
    private function seedFirstConfig(): void
    {
        $old = \Illuminate\Support\Facades\DB::table('email_settings')->first();

        if (! $old || blank($old->host)) {
            return;
        }

        \Illuminate\Support\Facades\DB::table('email_configs')->insert([
            'name' => 'Default SMTP',
            'provider' => 'custom',
            'host' => $old->host,
            'port' => $old->port ?: 587,
            'username' => $old->username,
            'password' => $old->password,          // already encrypted with the same app key
            'encryption' => $old->encryption,
            'from_name' => $old->from_name,
            'from_email' => $old->from_address ?: 'no-reply@localhost',
            'is_default' => true,
            'is_active' => (bool) $old->is_enabled,
            'priority' => 10,
            'health' => 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropColumn(['category', 'body_text', 'description', 'is_system']);
        });

        foreach ([
            'email_campaign_recipients', 'email_campaigns', 'email_notification_rules',
            'email_suppressions', 'email_spam_reports', 'email_bounces',
            'email_clicks', 'email_opens', 'email_attachments', 'email_logs', 'email_configs',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
