<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MapsLead store for the Maps collector.
 *
 * `place_key` is the deduplication anchor. The extension derives it from the
 * most stable identifier Google exposes for a listing (feature id, then place
 * id, then CID, then coordinates, then a name+address hash), so the unique
 * index below is what actually guarantees one row per business.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maps_leads', function (Blueprint $table) {
            $table->id();

            // --- identity -----------------------------------------------
            $table->string('place_key', 191)->unique();
            $table->string('name');
            $table->text('maps_url');

            // --- publicly visible business details ----------------------
            $table->string('category')->nullable();
            $table->string('address', 512)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('website', 512)->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('plus_code', 64)->nullable();
            $table->string('price_level', 16)->nullable();
            $table->string('business_status', 64)->nullable();
            $table->json('opening_hours')->nullable();

            // --- provenance ---------------------------------------------
            $table->string('source', 32)->default('google_maps');
            $table->string('search_country')->nullable();
            $table->string('search_city')->nullable();
            $table->string('search_category')->nullable();
            $table->string('search_query')->nullable();
            $table->unsignedInteger('position')->nullable();
            $table->string('first_run_id', 64)->nullable();
            $table->string('last_run_id', 64)->nullable();
            $table->unsignedInteger('times_seen')->default(1);
            $table->timestamp('collected_at')->nullable();

            // --- lead management ----------------------------------------
            $table->string('status', 32)->default('new'); // new|contacted|qualified|won|lost
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['search_country', 'search_city']);
            $table->index('search_category');
            $table->index('category');
            $table->index('phone');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maps_leads');
    }
};
