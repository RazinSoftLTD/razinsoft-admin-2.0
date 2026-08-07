<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // Which shelf the assistant's reply came from: faq | openai | handover. The reply
            // list is only diagnosable when it says which path produced each line.
            $table->string('ai_source', 20)->nullable()->after('ai_generated');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', fn (Blueprint $t) => $t->dropColumn('ai_source'));
    }
};
