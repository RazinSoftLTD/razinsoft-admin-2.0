<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Separate ordering for the website homepage's featured products (drag-and-drop in admin). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('home_order')->default(0)->after('sort_order');
        });

        // Seed home_order from the current sort_order so the first homepage order is stable.
        \App\Models\Product::query()->update(['home_order' => \Illuminate\Support\Facades\DB::raw('sort_order')]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('home_order');
        });
    }
};
