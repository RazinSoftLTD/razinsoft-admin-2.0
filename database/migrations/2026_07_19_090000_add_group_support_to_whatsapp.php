<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_chats', function (Blueprint $table) {
            // 'single' = one-to-one chat, 'group' = a WhatsApp group.
            $table->string('chat_type', 16)->default('single')->after('phone');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // In a group we need to know which participant sent the message.
            $table->string('sender_name')->nullable()->after('direction');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_chats', function (Blueprint $table) {
            $table->dropColumn('chat_type');
        });
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn('sender_name');
        });
    }
};
