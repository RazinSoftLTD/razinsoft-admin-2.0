<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace rebuild — drops the old Phase-1 module entirely and recreates a clean,
 * desk-style (Worksuite-like) schema: projects (with optional child projects),
 * members, milestones, files, tasks (+subtasks), task comments and an activity log.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Old module: gone for good. ----
        foreach ([
            'project_activity_logs', 'project_change_requests', 'project_members', 'project_documents',
            'project_checklist_items', 'checklist_templates', 'project_tasks', 'project_workstreams', 'projects',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();          // short code, auto e.g. PRJ-0007
            $table->string('name');
            $table->foreignId('parent_id')->nullable();     // optional child project
            $table->foreignId('client_id')->nullable();     // users.id (customer)
            $table->string('category')->nullable();         // Website / Mobile App / …
            $table->string('status', 30)->default('todo');  // todo | in_progress | on_hold | completed | cancelled
            $table->string('priority', 20)->default('medium');
            $table->date('start_date')->nullable();
            $table->date('deadline')->nullable();           // null → no deadline
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('currency', 8)->default('USD');
            $table->unsignedInteger('hours_allocated')->nullable();
            $table->boolean('auto_progress')->default(true); // true → % from completed tasks
            $table->unsignedTinyInteger('progress')->default(0); // manual % when auto is off
            $table->text('summary')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('project_manager_id')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->foreignId('user_id');
            $table->unique(['project_id', 'user_id']);
            $table->timestamps();
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('status', 20)->default('incomplete'); // incomplete | complete
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->string('name');                 // original filename
            $table->string('path');                 // storage path
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable();
            $table->timestamps();
        });

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->foreignId('milestone_id')->nullable();
            $table->foreignId('parent_id')->nullable();  // subtask
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('todo');   // backlog | todo | in_progress | review | completed | cancelled
            $table->string('priority', 20)->default('medium');
            $table->foreignId('assigned_to')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('project_task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id');
            $table->foreignId('user_id');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('project_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->foreignId('user_id')->nullable();
            $table->string('action', 40);           // created / status / task / milestone / member / file / comment …
            $table->string('description', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'project_activity_logs', 'project_task_comments', 'project_tasks', 'project_files',
            'project_milestones', 'project_members', 'projects',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
