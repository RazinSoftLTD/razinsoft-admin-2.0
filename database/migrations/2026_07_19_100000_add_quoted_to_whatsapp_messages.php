<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // No ->after('sender_jid'): that column arrives in a LATER migration (2026_07_20_170000),
            // so on a fresh database this failed with "Unknown column 'sender_jid'". Existing
            // databases already ran this; column order is cosmetic, so dropping the clause is safe.
            $table->string('quoted_id')->nullable();                        // wa_message_id of the replied-to message
            $table->text('quoted_body')->nullable()->after('quoted_id');    // snippet of the replied message
            $table->string('quoted_sender')->nullable()->after('quoted_body'); // who sent the replied message
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn(['quoted_id', 'quoted_body', 'quoted_sender']);
        });
    }
};
