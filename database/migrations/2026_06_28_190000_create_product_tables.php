<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->string('category')->nullable();
            $table->string('badge')->nullable();            // best_seller | new | free
            $table->string('status')->default('draft');     // draft | published
            $table->boolean('is_featured')->default(false);
            $table->string('version')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('ext_price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');

            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->unsignedInteger('sales_count')->default(0);

            $table->string('thumbnail')->nullable();
            $table->string('thumbnail_alt')->nullable();
            $table->string('hero_image')->nullable();
            $table->string('hero_alt')->nullable();

            $table->longText('overview')->nullable();        // rich long description (SEO + content)

            $table->string('live_demo_url')->nullable();
            $table->string('admin_demo_url')->nullable();
            $table->string('customer_demo_url')->nullable();
            $table->string('android_apk_url')->nullable();
            $table->string('ios_ipa_url')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gallery_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');                          // Website / Admin / App ...
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('gallery_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_group_id')->constrained()->cascadeOnDelete();
            $table->string('image');
            $table->string('caption')->nullable();
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');                          // Basic / Standard / Premium ...
            $table->text('blurb')->nullable();               // short paragraph
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_popular')->default(false);
            $table->json('perks')->nullable();               // ["Single domain", "Email support", ...]
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_tech', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');                          // Laravel, PHP, Flutter ...
            $table->string('color')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_suitable_for', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_docs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title');                         // Installation Guide ...
            $table->string('type')->nullable();
            $table->string('url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('answer')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // One shared source code per product (versioned). Same download for every plan.
        Schema::create('product_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->string('file_path');                     // stored zip
            $table->string('size')->nullable();
            $table->text('changelog')->nullable();
            $table->boolean('is_latest')->default(true);
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('comment')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('product_files');
        Schema::dropIfExists('product_faqs');
        Schema::dropIfExists('product_docs');
        Schema::dropIfExists('product_suitable_for');
        Schema::dropIfExists('product_tech');
        Schema::dropIfExists('features');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('gallery_images');
        Schema::dropIfExists('gallery_groups');
        Schema::dropIfExists('products');
    }
};
