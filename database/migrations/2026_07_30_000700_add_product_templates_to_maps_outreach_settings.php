<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product outreach templates.
 *
 * One letter for every trade reads as a blast, so each product line gets its
 * own. Stored as product => template key; anything unmapped falls back to
 * `template_key`, which stays the general-purpose default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maps_outreach_settings', function (Blueprint $table) {
            $table->json('product_templates')->nullable()->after('template_key');
        });
    }

    public function down(): void
    {
        Schema::table('maps_outreach_settings', function (Blueprint $table) {
            $table->dropColumn('product_templates');
        });
    }
};
