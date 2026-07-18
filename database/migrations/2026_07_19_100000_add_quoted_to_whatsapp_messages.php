<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('quoted_id')->nullable()->after('sender_jid');   // wa_message_id of the replied-to message
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
