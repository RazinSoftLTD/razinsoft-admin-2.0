<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Country (from IP geolocation) for website visits, including anonymous visitors. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('client_activity_logs', 'country')) {
                $table->string('country', 100)->nullable()->after('client_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('client_activity_logs', 'country')) {
                $table->dropColumn('country');
            }
        });
    }
};
