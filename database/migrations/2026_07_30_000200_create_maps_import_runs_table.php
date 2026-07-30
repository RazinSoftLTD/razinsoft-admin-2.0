<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Import history: one row per collection run started in the extension.
 * The extension's `run_id` is the correlation key across leads and logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maps_import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32)->default('google_maps');

            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('category')->nullable();
            $table->string('query')->nullable();

            $table->unsignedInteger('received')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('rejected')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maps_import_runs');
    }
};
