<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Track error-page views (404 & friends) separately from normal visits. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('client_activity_logs', 'error_code')) {
                $table->unsignedSmallInteger('error_code')->nullable()->after('title')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('client_activity_logs', 'error_code')) {
                $table->dropColumn('error_code');
            }
        });
    }
};
