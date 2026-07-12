<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->string('project_type')->nullable()->after('title');   // Web App, Mobile App, SaaS, …
            $table->string('priority')->default('medium')->after('stage'); // high/medium/low
            $table->unsignedTinyInteger('probability')->nullable()->after('priority'); // 0–100 (null = stage default)
            $table->string('lost_reason')->nullable()->after('probability');
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
        });

        // Timeline of notes / calls / meetings / emails logged against a deal.
        Schema::create('deal_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('note');   // note | call | meeting | email | stage
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_activities');
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['project_type', 'priority', 'probability', 'lost_reason', 'won_at', 'lost_at']);
        });
    }
};
