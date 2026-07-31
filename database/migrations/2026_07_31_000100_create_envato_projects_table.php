<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A named set of competing products to watch side by side.
 *
 * The watchlist answers "what is this author selling". A project answers the
 * question that actually drives a build decision: "against the products we would
 * be competing with, how are we doing" — so it deliberately cuts across authors
 * rather than grouping by them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envato_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('notes')->nullable();
            /** Our own item, when we have one in the race. Highlighted in the compare. */
            $table->foreignId('own_product_id')->nullable()
                ->constrained('envato_products')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('envato_project_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envato_project_id')->constrained('envato_projects')->cascadeOnDelete();
            $table->foreignId('envato_product_id')->constrained('envato_products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['envato_project_id', 'envato_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envato_project_products');
        Schema::dropIfExists('envato_projects');
    }
};
