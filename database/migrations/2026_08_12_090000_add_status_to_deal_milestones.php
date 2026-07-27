<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a deal milestone be marked done or called off.
 *
 * The date is kept alongside the status rather than derived from updated_at: editing the title a
 * week later would move it, and "when was this delivered" is a question the answer has to survive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_milestones', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('due_date');
            $table->timestamp('completed_at')->nullable()->after('status');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            // Who decided, so the trail is not anonymous.
            $table->foreignId('status_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deal_milestones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_by');
            $table->dropColumn(['status', 'completed_at', 'cancelled_at']);
        });
    }
};
