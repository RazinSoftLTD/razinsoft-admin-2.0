<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records that a customer has had their welcome email, so signing in again does not send another.
 *
 * A column rather than a lookup in email_logs: this is asked on every sign-in, and it stays true
 * even after old log rows are pruned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('welcomed_at')->nullable()->after('last_seen_at');
        });

        // Everyone who already has an account counts as welcomed. Without this, the next sign-in
        // of every existing customer — including the hundreds that arrived by import — would send
        // a "Welcome!" to someone who has been a customer for years. To include them deliberately,
        // clear the column for whichever accounts should get it.
        // Two passes rather than COALESCE(created_at, NOW()): NOW() does not exist in SQLite,
        // which is what the tests run on.
        DB::table('users')->where('role', 'customer')
            ->whereNull('welcomed_at')->whereNotNull('created_at')
            ->update(['welcomed_at' => DB::raw('created_at')]);

        DB::table('users')->where('role', 'customer')
            ->whereNull('welcomed_at')
            ->update(['welcomed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('welcomed_at');
        });
    }
};
