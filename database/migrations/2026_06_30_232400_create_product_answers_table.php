<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();          // answerer name snapshot
            $table->text('body');
            $table->boolean('is_admin')->default(false); // admin/author reply → "Author" badge
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        // Questions are now threads — answers live in product_answers.
        Schema::table('product_questions', function (Blueprint $table) {
            $table->dropColumn(['answer', 'answered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_answers');
        Schema::table('product_questions', function (Blueprint $table) {
            $table->text('answer')->nullable();
            $table->timestamp('answered_at')->nullable();
        });
    }
};
