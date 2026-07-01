<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->string('image')->nullable();
            $table->string('category')->nullable();
            $table->string('author')->nullable();
            $table->date('published_at')->nullable();
            $table->string('read_time')->nullable();
            $table->json('tags')->nullable();
            $table->json('content')->nullable();       // array of paragraphs
            $table->string('quote', 1000)->nullable(); // optional pull-quote
            $table->json('takeaways')->nullable();      // optional key takeaways
            $table->boolean('is_featured')->default(false);
            $table->string('status')->default('draft'); // draft | published
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
