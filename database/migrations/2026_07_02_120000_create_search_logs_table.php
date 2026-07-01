<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('term');                              // normalised (lowercased) search term
            $table->unsignedInteger('results_count')->default(0); // how many results it returned (0 = no match)
            $table->string('source')->default('products');        // where the search happened
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index('term');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
