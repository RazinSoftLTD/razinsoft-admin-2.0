<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_chats', function (Blueprint $table) {
            // When, not whether: the most recently pinned chat sits above the others, the same
            // way WhatsApp orders its own pins.
            $table->timestamp('pinned_at')->nullable()->after('status');
            $table->timestamp('blocked_at')->nullable()->after('pinned_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_chats', function (Blueprint $table) {
            $table->dropColumn(['pinned_at', 'blocked_at']);
        });
    }
};
