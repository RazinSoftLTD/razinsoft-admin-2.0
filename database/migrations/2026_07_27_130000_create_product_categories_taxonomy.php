<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One shared Product Category / Sub-category list (Settings → CRM Settings) that Leads,
 * Deals and Clients all pick from, instead of each module inventing its own values.
 * A row with parent_id = null is a category; with a parent it is that category's sub-category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        // Leads and deals gain the same pair the clients table already had.
        Schema::table('leads', function (Blueprint $table) {
            $table->string('product_category')->nullable()->after('industry');
            $table->string('product_sub_category')->nullable()->after('product_category');
        });
        Schema::table('deals', function (Blueprint $table) {
            $table->string('product_category')->nullable()->after('project_type');
            $table->string('product_sub_category')->nullable()->after('product_category');
        });

        // Seed the taxonomy from whatever clients already use, so nothing is lost.
        $existing = \Illuminate\Support\Facades\DB::table('users')
            ->whereNotNull('client_category')->where('client_category', '!=', '')
            ->select('client_category', 'client_sub_category')->distinct()->get();

        $catIds = [];
        foreach ($existing as $row) {
            $cat = trim((string) $row->client_category);
            if ($cat === '') {
                continue;
            }
            $catIds[$cat] ??= \App\Models\ProductCategory::firstOrCreate(['parent_id' => null, 'name' => $cat])->id;

            $sub = trim((string) $row->client_sub_category);
            if ($sub !== '') {
                \App\Models\ProductCategory::firstOrCreate(['parent_id' => $catIds[$cat], 'name' => $sub]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['product_category', 'product_sub_category']);
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['product_category', 'product_sub_category']);
        });
        Schema::dropIfExists('product_categories');
    }
};
