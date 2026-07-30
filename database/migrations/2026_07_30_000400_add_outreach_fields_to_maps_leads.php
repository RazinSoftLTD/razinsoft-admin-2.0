<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email discovery + outreach state for collected Maps leads.
 *
 * Google Maps never shows an email address, so one has to be looked up from the
 * business's own website afterwards. These columns record that lookup and what
 * was mailed, so the same lead is never contacted twice and a lead that opted
 * out is never contacted again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maps_leads', function (Blueprint $table) {
            // --- discovery -------------------------------------------------
            $table->string('email')->nullable()->after('phone');
            $table->string('email_source')->nullable()->after('email');   // website | manual
            $table->string('email_status')->default('pending')->after('email_source');
            $table->timestamp('email_checked_at')->nullable()->after('email_status');
            $table->unsignedTinyInteger('email_attempts')->default(0)->after('email_checked_at');

            // --- outreach --------------------------------------------------
            $table->string('outreach_status')->nullable()->after('email_attempts');
            $table->timestamp('outreach_sent_at')->nullable()->after('outreach_status');
            $table->string('outreach_error')->nullable()->after('outreach_sent_at');

            /**
             * Per-lead unsubscribe handle. Random rather than derived from the id
             * so one link cannot be edited into another lead's link.
             */
            $table->string('unsubscribe_token', 40)->nullable()->unique()->after('outreach_error');

            $table->index('email');
            $table->index(['email_status', 'outreach_status']);
        });
    }

    public function down(): void
    {
        Schema::table('maps_leads', function (Blueprint $table) {
            $table->dropIndex(['email_status', 'outreach_status']);
            $table->dropIndex(['email']);
            $table->dropColumn([
                'email', 'email_source', 'email_status', 'email_checked_at', 'email_attempts',
                'outreach_status', 'outreach_sent_at', 'outreach_error', 'unsubscribe_token',
            ]);
        });
    }
};
