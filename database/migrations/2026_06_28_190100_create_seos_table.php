<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reusable polymorphic SEO record (products now; blog/pages later).
        Schema::create('seos', function (Blueprint $table) {
            $table->id();
            $table->morphs('seoable');                       // seoable_type + seoable_id (indexed)

            // Meta / on-page
            $table->string('seo_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('focus_keyword')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('robots')->default('index,follow');

            // Open Graph
            $table->string('og_title')->nullable();
            $table->string('og_description', 320)->nullable();
            $table->string('og_image')->nullable();

            // Twitter
            $table->string('twitter_title')->nullable();
            $table->string('twitter_description', 320)->nullable();
            $table->string('twitter_image')->nullable();
            $table->string('twitter_card')->default('summary_large_image');

            // Structured data (Product + SoftwareApplication)
            $table->string('brand')->nullable();
            $table->string('sku')->nullable();
            $table->string('operating_system')->nullable();
            $table->string('application_category')->nullable();
            $table->string('software_version')->nullable();
            $table->date('price_valid_until')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seos');
    }
};
