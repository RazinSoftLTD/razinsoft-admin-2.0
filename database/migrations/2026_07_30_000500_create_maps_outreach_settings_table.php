<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Single-row settings for automatic outreach to collected Maps leads. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maps_outreach_settings', function (Blueprint $table) {
            $table->id();

            /** Master switch. Off by default: nothing sends until it is turned on. */
            $table->boolean('is_enabled')->default(false);

            /** Look up emails from lead websites at all. */
            $table->boolean('discover_emails')->default(true);

            /** Send automatically once an address is found, rather than waiting for review. */
            $table->boolean('auto_send')->default(false);

            /** Template key used for the message, and which SMTP account sends it. */
            $table->string('template_key')->default('maps_lead_outreach');
            $table->foreignId('email_config_id')->nullable();

            /**
             * Volume guards. Sending thousands of cold messages in an hour is the
             * fastest way to get a domain blocked, so both a daily ceiling and a
             * gap between messages are enforced.
             */
            $table->unsignedSmallInteger('daily_limit')->default(50);
            $table->unsignedSmallInteger('min_gap_seconds')->default(90);

            /** Only mail leads whose search country is in this list (empty = any). */
            $table->json('allowed_countries')->nullable();

            /** Bookkeeping for the daily cap. */
            $table->date('quota_date')->nullable();
            $table->unsignedSmallInteger('quota_used')->default(0);

            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maps_outreach_settings');
    }
};
