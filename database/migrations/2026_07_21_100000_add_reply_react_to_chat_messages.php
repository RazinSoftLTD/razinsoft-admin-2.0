<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // The message this one replies to (WhatsApp-style quote). Null = not a reply.
            $table->foreignId('reply_to_id')->nullable()->after('user_id')
                ->constrained('chat_messages')->nullOnDelete();
            // Emoji reactions keyed by user id: {"12": "👍", "5": "❤️"}.
            $table->json('reactions')->nullable()->after('attachment_name');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_to_id');
            $table->dropColumn('reactions');
        });
    }
};
