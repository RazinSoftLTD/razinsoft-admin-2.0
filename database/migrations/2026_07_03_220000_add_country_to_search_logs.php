<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_logs', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('source'); // ISO-3166 alpha-2 (from Cloudflare)
            $table->string('ip', 45)->nullable()->after('country_code');
            $table->index('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('search_logs', function (Blueprint $table) {
            $table->dropIndex(['country_code']);
            $table->dropColumn(['country_code', 'ip']);
        });
    }
};
