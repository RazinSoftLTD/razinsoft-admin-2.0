<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every address found on a lead's website, not just the best one.
 *
 * `maps_leads.email` still holds the one chosen to contact, so nothing that
 * reads it has to change; this table is the full picture behind that choice.
 *
 * `is_generic` is the column that matters most. A shared inbox (info@, sales@)
 * is a business contact; rahim@company.com is a person, and unsolicited mail to
 * a named individual sits on completely different legal ground and draws far
 * more complaints. Outreach only ever uses the generic ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maps_lead_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maps_lead_id')->constrained('maps_leads')->cascadeOnDelete();

            $table->string('email');
            /** The page it was found on, so a questionable address can be checked. */
            $table->string('source_url', 500)->nullable();

            $table->boolean('is_generic')->default(false);
            $table->boolean('same_domain')->default(false);

            $table->timestamps();

            // One row per address per lead; a crawl re-run must not duplicate.
            $table->unique(['maps_lead_id', 'email']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maps_lead_emails');
    }
};
