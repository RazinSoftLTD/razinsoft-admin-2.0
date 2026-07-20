<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_task_timers', function (Blueprint $table) {
            // Seconds banked from earlier runs; started_at is null while paused.
            $table->unsignedInteger('banked_seconds')->default(0)->after('user_id');
            $table->timestamp('started_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_task_timers', fn (Blueprint $t) => $t->dropColumn('banked_seconds'));
    }
};
