<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Settings switch — when off, no time UI is shown anywhere in the project.
            $table->boolean('time_tracking')->default(false)->after('needs_requirements');
        });

        Schema::create('project_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('minutes');
            $table->date('spent_on');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'spent_on']);
            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_time_logs');
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn('time_tracking'));
    }
};
