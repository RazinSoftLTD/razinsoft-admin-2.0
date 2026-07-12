<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            // Drop the old enum constraint → plain string so new statuses are allowed.
            $table->string('status')->default('pending')->change();
            $table->timestamp('seen_at')->nullable()->after('status'); // null = new/unread booking
        });

        // Existing meetings are considered already seen (only brand-new bookings should be "unread").
        DB::table('meetings')->whereNull('seen_at')->update(['seen_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('seen_at');
        });
    }
};
