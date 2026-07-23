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
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('countdown_title_color', 7)->default('#4f46e5')->after('countdown_label');
            $table->string('countdown_value_color', 7)->default('#4f46e5')->after('countdown_title_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['countdown_title_color', 'countdown_value_color']);
        });
    }
};
