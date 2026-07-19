<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which PRD sections this project collects (keys from Project::PRD_SECTIONS).
        Schema::table('projects', function (Blueprint $table) {
            $table->json('prd_sections')->nullable()->after('needs_requirements');
        });

        Schema::create('project_prd_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('section');                 // Project::PRD_SECTIONS key
            $table->string('name')->nullable();        // original file name
            $table->string('path')->nullable();        // null for note-only entries
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_prd_items');
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn('prd_sections'));
    }
};
