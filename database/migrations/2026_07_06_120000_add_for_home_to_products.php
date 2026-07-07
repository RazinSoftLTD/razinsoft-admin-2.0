<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('for_home')->default(false)->after('is_featured');
        });

        // Seed a sensible default so the homepage isn't empty: mark the first 6 featured
        // (or newest) published products for the homepage.
        $ids = DB::table('products')->where('status', 'published')
            ->orderByDesc('is_featured')->orderBy('sort_order')->limit(6)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('products')->whereIn('id', $ids)->update(['for_home' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('for_home');
        });
    }
};
