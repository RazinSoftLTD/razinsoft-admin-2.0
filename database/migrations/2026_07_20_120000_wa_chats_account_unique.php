<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_chats', function (Blueprint $table) {
            // The same wa_id can exist under different accounts, so uniqueness must include the account.
            $table->dropUnique('whatsapp_chats_wa_id_unique');
            $table->unique(['account_id', 'wa_id']);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_chats', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'wa_id']);
            $table->unique('wa_id');
        });
    }
};
