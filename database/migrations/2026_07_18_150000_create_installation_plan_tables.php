<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Installation Plans — per-product service packages (Basic / Pro / Enterprise style)
 * with a shared feature list and a plan × feature inclusion matrix, exactly like the
 * comparison table on the public Installation page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id');
            $table->string('label');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'position']);
        });

        Schema::create('installation_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id');
            $table->string('name');                 // Basic / Pro / Enterprise
            $table->string('tagline')->nullable();  // "Elevate Your Business"
            $table->decimal('price', 10, 2)->default(0);       // regular price (strikethrough)
            $table->decimal('sale_price', 10, 2)->nullable();  // discounted price shown big
            $table->string('note')->nullable();     // "2 Revisions (+$200 per app)"
            $table->boolean('is_popular')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'position']);
        });

        Schema::create('installation_plan_feature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id');
            $table->foreignId('feature_id');
            $table->unique(['plan_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_plan_feature');
        Schema::dropIfExists('installation_plans');
        Schema::dropIfExists('installation_features');
    }
};
