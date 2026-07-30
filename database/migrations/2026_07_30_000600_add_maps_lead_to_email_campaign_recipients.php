<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a campaign go to collected Maps leads, not only registered clients.
 *
 * A recipient is one or the other: `user_id` for an account, `maps_lead_id` for
 * a lead the Google Maps collector found. Both are nullable, and exactly one is
 * set — a lead is not a user and pointing `user_id` at a maps_leads row would
 * both break the foreign key and silently attribute mail to the wrong person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaign_recipients', function (Blueprint $table) {
            $table->foreignId('maps_lead_id')->nullable()->after('user_id')
                ->constrained('maps_leads')->nullOnDelete();

            // Campaign progress and the per-lead engagement view both read this.
            $table->index(['email_campaign_id', 'maps_lead_id'], 'ecr_campaign_lead_idx');
        });
    }

    public function down(): void
    {
        Schema::table('email_campaign_recipients', function (Blueprint $table) {
            $table->dropIndex('ecr_campaign_lead_idx');
            $table->dropConstrainedForeignId('maps_lead_id');
        });
    }
};
