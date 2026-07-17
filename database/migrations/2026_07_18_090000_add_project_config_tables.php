<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project configuration: DB-driven categories + per-project board columns.
 * A column with project_id = NULL is a global default; every new project copies
 * those as its own columns, which the project can then add to / rename / recolour.
 */
return new class extends Migration
{
    /** The out-of-the-box board columns (also the global defaults). */
    public const DEFAULT_COLUMNS = [
        ['key' => 'backlog', 'name' => 'Backlog', 'color' => '#94a3b8', 'is_done' => false, 'is_excluded' => false],
        ['key' => 'todo', 'name' => 'To Do', 'color' => '#0ea5e9', 'is_done' => false, 'is_excluded' => false],
        ['key' => 'in_progress', 'name' => 'In Progress', 'color' => '#3b82f6', 'is_done' => false, 'is_excluded' => false],
        ['key' => 'review', 'name' => 'Review', 'color' => '#a855f7', 'is_done' => false, 'is_excluded' => false],
        ['key' => 'completed', 'name' => 'Done', 'color' => '#10b981', 'is_done' => true, 'is_excluded' => false],
        ['key' => 'cancelled', 'name' => 'Cancelled', 'color' => '#9ca3af', 'is_done' => false, 'is_excluded' => true],
    ];

    public function up(): void
    {
        Schema::create('project_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('project_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable();   // null = global default template
            $table->string('key', 40);
            $table->string('name');
            $table->string('color', 20)->default('#94a3b8');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_done')->default(false);      // counts as complete → sets completed_at
            $table->boolean('is_excluded')->default(false);  // excluded from progress (e.g. Cancelled)
            $table->timestamps();
            $table->index(['project_id', 'position']);
        });

        // Seed categories from the previous hard-coded list.
        $cats = ['Website Development', 'Mobile App Development', 'Full Web + Mobile Solution', 'UI/UX Design',
            'Installation & Setup', 'App Publishing', 'Customization', 'Maintenance & Support', 'API Integration',
            'Server / DevOps', 'Marketing', 'Other'];
        foreach ($cats as $i => $name) {
            DB::table('project_categories')->insert(['name' => $name, 'position' => $i, 'created_at' => now(), 'updated_at' => now()]);
        }

        // Global default columns.
        $this->seedColumns(null);

        // Give every existing project its own copy.
        foreach (DB::table('projects')->pluck('id') as $projectId) {
            $this->seedColumns($projectId);
        }
    }

    private function seedColumns(?int $projectId): void
    {
        foreach (self::DEFAULT_COLUMNS as $i => $col) {
            DB::table('project_columns')->insert([
                'project_id' => $projectId,
                'key' => $col['key'],
                'name' => $col['name'],
                'color' => $col['color'],
                'position' => $i,
                'is_done' => $col['is_done'],
                'is_excluded' => $col['is_excluded'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_columns');
        Schema::dropIfExists('project_categories');
    }
};
