<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single row: the Google key and how far a search is allowed to go.
        Schema::create('place_settings', function (Blueprint $table) {
            $table->id();
            $table->text('api_key')->nullable();                       // encrypted by the model
            $table->unsignedSmallInteger('max_pages')->default(3);     // 20 results per page
            $table->boolean('auto_crawl_websites')->default(true);     // chase emails after a search
            $table->timestamps();
        });

        // One search: "software company" in Dhaka, Bangladesh.
        Schema::create('place_searches', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('query');                                   // what was actually sent
            $table->string('status')->default('pending')->index();     // pending|running|done|failed
            $table->unsignedInteger('results_found')->default(0);
            $table->unsignedInteger('results_new')->default(0);
            $table->unsignedSmallInteger('api_calls')->default(0);     // what this cost, in requests
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('place_leads', function (Blueprint $table) {
            $table->id();
            // Google's own id for the business — the one field their terms allow keeping
            // indefinitely, and the thing that makes a repeat search update rather than duplicate.
            $table->string('place_id')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('phone', 32)->nullable()->index();
            $table->string('website', 512)->nullable();
            $table->string('address', 512)->nullable();
            $table->string('city')->nullable()->index();
            $table->string('country')->nullable()->index();
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('reviews')->default(0);
            $table->foreignId('search_id')->nullable()->constrained('place_searches')->nullOnDelete();
            // Set once the site has been handed to the email crawler, so it is not queued twice.
            $table->timestamp('website_crawled_at')->nullable();
            $table->foreignId('imported_client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_leads');
        Schema::dropIfExists('place_searches');
        Schema::dropIfExists('place_settings');
    }
};
