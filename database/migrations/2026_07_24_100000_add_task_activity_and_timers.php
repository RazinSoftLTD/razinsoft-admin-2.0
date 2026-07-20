<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Activity rows can now belong to a task, so the task page has its own timeline.
        Schema::table('project_activity_logs', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->after('project_id')->constrained('project_tasks')->cascadeOnDelete();
        });

        // One running timer per user per task; stopping it writes a project_time_logs row.
        Schema::create('project_task_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamps();
            $table->unique(['task_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_timers');
        Schema::table('project_activity_logs', fn (Blueprint $t) => $t->dropConstrainedForeignId('task_id'));
    }
};
