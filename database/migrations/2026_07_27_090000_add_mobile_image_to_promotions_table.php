<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An optional narrower artwork for the Top Banner. The desktop banner is 16:1, so on a
 * phone it has to be cropped to a fragment; this lets the admin upload a 3:1 version
 * that phones use instead. Blank = keep using the desktop image.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('mobile_image')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('mobile_image');
        });
    }
};
