<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', fn (Blueprint $t) => $t->json('labels')->nullable()->after('priority'));

        Schema::create('project_task_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_files');
        Schema::table('project_tasks', fn (Blueprint $t) => $t->dropColumn('labels'));
    }
};
