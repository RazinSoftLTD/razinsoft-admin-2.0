<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('password');
        });

        // Backfill: previously login_enabled=false meant "cannot sign in" => blocked.
        DB::table('users')->where('login_enabled', false)->update(['status' => 'blocked']);
        DB::table('users')->where('login_enabled', true)->update(['status' => 'active']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('login_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('login_enabled')->default(true)->after('password');
        });
        DB::table('users')->where('status', 'blocked')->update(['login_enabled' => false]);
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
