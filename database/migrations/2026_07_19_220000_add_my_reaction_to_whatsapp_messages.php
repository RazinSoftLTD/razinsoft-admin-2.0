<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // `reaction` = the other party's reaction; `my_reaction` = ours. Both can show at once (WhatsApp-style).
            $table->string('my_reaction', 16)->nullable()->after('reaction');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn('my_reaction');
        });
    }
};
