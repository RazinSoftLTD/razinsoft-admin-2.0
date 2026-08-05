<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            // The Products-menu offer is words, not artwork, so it needs somewhere to keep them.
            // One JSON column rather than a dozen nullable ones the other two types never use.
            $table->json('content')->nullable()->after('type');
        });

        // The menu offer has no artwork, and image has always been NOT NULL because the two
        // existing types both need one.
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('image')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
