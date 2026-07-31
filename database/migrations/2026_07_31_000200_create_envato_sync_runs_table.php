<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A log of every CodeCanyon sync attempt.
 *
 * The Envato API has no sales history, so a snapshot missed on a given day is
 * gone for good. That makes "did the sync actually run?" a question worth being
 * able to answer, rather than inferring it from a gap in a chart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envato_sync_runs', function (Blueprint $table) {
            $table->id();
            // schedule | catch-up | manual | author
            $table->string('trigger', 20)->default('manual');
            // queued | running | success | failed
            $table->string('status', 20)->default('queued');
            // Set only for a single-author refresh; null means the whole watchlist.
            $table->foreignId('envato_author_id')->nullable()->constrained('envato_authors')->nullOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('authors_synced')->default(0);
            $table->unsignedInteger('products_synced')->default(0);
            $table->unsignedInteger('snapshots_written')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envato_sync_runs');
    }
};
