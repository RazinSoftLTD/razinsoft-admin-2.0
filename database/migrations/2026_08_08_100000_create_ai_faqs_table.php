<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The first stop before OpenAI: a keyword hit answers from here, instantly and free.
        Schema::create('ai_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('keywords');           // comma-separated; any one matching fires
            $table->text('reply');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('hit_count')->default(0);   // which entries earn their place
            $table->timestamps();
        });

        Schema::table('whatsapp_chats', function (Blueprint $table) {
            // Set when the assistant hands a conversation to the team; while fresh, it stays out.
            $table->timestamp('ai_handover_at')->nullable()->after('blocked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_faqs');
        Schema::table('whatsapp_chats', fn (Blueprint $t) => $t->dropColumn('ai_handover_at'));
    }
};
