<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server side collection log. Written from the queue so logging never slows the
 * ingest response down.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maps_collection_logs', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 64)->nullable();
            $table->string('level', 16)->default('info'); // info|warning|error
            $table->string('event', 64);                  // stored|duplicate|rejected|...
            $table->string('message', 512)->nullable();
            $table->string('place_key', 191)->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('maps_leads')->nullOnDelete();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['run_id', 'level']);
            $table->index('event');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maps_collection_logs');
    }
};
