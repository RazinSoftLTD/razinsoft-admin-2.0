<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Square icon for the product chips on the public Installation Plans page. The product
 * thumbnail is 3:2, so it looked squashed in the small square chip; this is a proper icon.
 * Blank = fall back to the thumbnail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('installation_icon')->nullable()->after('installation_status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('installation_icon');
        });
    }
};
