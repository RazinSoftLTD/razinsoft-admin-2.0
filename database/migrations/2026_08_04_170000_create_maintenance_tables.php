<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A maintenance contract. project_id is optional on purpose: some contracts cover work this
        // panel never tracked as a project, and refusing those would push them back into a
        // spreadsheet, which is what this replaces.
        Schema::create('maintenance_projects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('status')->default('active');       // active | paused | ended
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('fee', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('billing_cycle')->default('monthly'); // monthly | quarterly | yearly | one_off
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('scope')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'ends_on']);
        });

        // The recurring plan. One row per repeating job, not per occurrence — a daily backup over a
        // year is one row here, and the occurrences are worked out from the dates.
        Schema::create('maintenance_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('frequency');                        // daily | weekly | monthly
            $table->unsignedTinyInteger('weekday')->nullable();  // 0-6, weekly only
            $table->unsignedTinyInteger('day_of_month')->nullable(); // 1-31, monthly only
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['maintenance_project_id', 'is_active']);
        });

        // One row per occurrence actually done. Absence of a row is what makes an occurrence due,
        // so nothing has to be generated in advance and no scheduler is needed for the panel to be
        // right — which matters here, because this server runs none.
        Schema::create('maintenance_task_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_task_id')->constrained()->cascadeOnDelete();
            $table->date('due_on');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['maintenance_task_id', 'due_on']);
        });

        // Renewal history. Each row is one term, so "when did this last renew and for how much"
        // survives the contract's dates being moved forward.
        Schema::create('maintenance_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_project_id')->constrained()->cascadeOnDelete();
            $table->date('previous_ends_on');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('fee', 12, 2)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('renewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_renewals');
        Schema::dropIfExists('maintenance_task_runs');
        Schema::dropIfExists('maintenance_tasks');
        Schema::dropIfExists('maintenance_projects');
    }
};
