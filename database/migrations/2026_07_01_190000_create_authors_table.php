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
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('role')->nullable();
            $table->string('photo')->nullable();
            $table->text('bio')->nullable();
            $table->timestamps();
        });

        // Backfill authors from the existing free-text names.
        foreach (DB::table('articles')->whereNotNull('author')->distinct()->pluck('author') as $name) {
            DB::table('authors')->insert([
                'name' => $name,
                'slug' => Str::slug($name),
                'role' => 'Content Writer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->after('author')->constrained('authors')->nullOnDelete();
        });

        foreach (DB::table('authors')->pluck('id', 'name') as $name => $id) {
            DB::table('articles')->where('author', $name)->update(['author_id' => $id]);
        }

        Schema::table('articles', fn (Blueprint $table) => $table->dropColumn('author'));
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('author')->nullable()->after('category_id');
        });
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_id');
        });
        Schema::dropIfExists('authors');
    }
};
