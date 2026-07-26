<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The top banner now sits inside the page container so its artwork lines up with the header,
 * which leaves a strip either side on wide screens. This is the colour that fills it — set it
 * to the artwork's edge colour and the band still reads as edge-to-edge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('banner_bg_color', 7)->nullable()->after('mobile_image');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('banner_bg_color');
        });
    }
};
