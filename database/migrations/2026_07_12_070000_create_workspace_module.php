<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace (Project Management) module — projects, workstreams, tasks/subtasks,
 * checklists (+ templates), documents, members, change requests and an activity log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('company')->nullable();
            $table->foreignId('sales_person_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('project_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('project_type')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('draft');
            $table->string('currency', 8)->default('BDT');
            $table->decimal('budget', 14, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('expected_delivery')->nullable();
            $table->date('actual_delivery')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_workstreams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->nullable();       // Website / Admin Panel / Android App / ...
            $table->string('status')->default('not_started');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workstream_id')->nullable()->constrained('project_workstreams')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('project_tasks')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo');
            $table->string('priority')->default('medium');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->string('project_type');
            $table->string('category')->nullable();     // e.g. Google Play, Apple Store
            $table->string('title');
            $table->boolean('required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('project_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('required')->default(true);
            $table->string('status')->default('waiting');
            $table->date('deadline')->nullable();
            $table->text('comment')->nullable();
            $table->string('attachment')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('Others');
            $table->string('name');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();       // Developer / QA / Designer / ...
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('project_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium');
            $table->decimal('estimated_cost', 14, 2)->nullable();
            $table->string('estimated_time')->nullable();
            $table->string('approval_status')->default('pending');
            $table->string('development_status')->default('not_started');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('project_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');                 // created / status / task / checklist / document / ...
            $table->string('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_activity_logs');
        Schema::dropIfExists('project_change_requests');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('project_documents');
        Schema::dropIfExists('project_checklist_items');
        Schema::dropIfExists('checklist_templates');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('project_workstreams');
        Schema::dropIfExists('projects');
    }
};
