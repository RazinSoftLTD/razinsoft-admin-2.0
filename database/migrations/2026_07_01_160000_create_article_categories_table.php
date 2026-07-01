<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Backfill categories from the existing free-text values.
        $names = DB::table('articles')->whereNotNull('category')->distinct()->pluck('category');
        foreach ($names as $name) {
            DB::table('article_categories')->insert([
                'name' => $name,
                'slug' => Str::slug($name),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('excerpt')->constrained('article_categories')->nullOnDelete();
        });

        // Link each article to its category, then drop the free-text column.
        foreach (DB::table('article_categories')->pluck('id', 'name') as $name => $id) {
            DB::table('articles')->where('category', $name)->update(['category_id' => $id]);
        }

        Schema::table('articles', fn (Blueprint $table) => $table->dropColumn('category'));
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('category')->nullable()->after('excerpt');
        });
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('article_categories');
    }
};
