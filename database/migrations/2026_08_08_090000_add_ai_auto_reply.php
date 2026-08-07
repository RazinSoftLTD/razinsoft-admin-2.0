<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            // Per-number switch: the user picks which lines the assistant answers on.
            $table->boolean('ai_reply_enabled')->default(false)->after('is_connected');
        });

        Schema::table('whatsapp_settings', function (Blueprint $table) {
            // One JSON blob for the behaviour knobs (mode, office hours, model, prompt) —
            // they change together from one settings card, and none is queried on its own.
            $table->json('ai_settings')->nullable()->after('interest_options');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // Marked on every reply the assistant writes, so the thread can say so — an AI
            // answer pretending to be a person is how trust in the inbox dies.
            $table->boolean('ai_generated')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', fn (Blueprint $t) => $t->dropColumn('ai_reply_enabled'));
        Schema::table('whatsapp_settings', fn (Blueprint $t) => $t->dropColumn('ai_settings'));
        Schema::table('whatsapp_messages', fn (Blueprint $t) => $t->dropColumn('ai_generated'));
    }
};
