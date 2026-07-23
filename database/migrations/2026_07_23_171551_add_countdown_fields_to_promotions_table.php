<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Top Banner only — whether the countdown-to-end-date timer shows, and its title text.
        Schema::table('promotions', function (Blueprint $table) {
            $table->boolean('countdown_enabled')->default(true)->after('ends_at');
            $table->string('countdown_label')->nullable()->after('countdown_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['countdown_enabled', 'countdown_label']);
        });
    }
};
