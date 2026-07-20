<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shared to-do items on a chat message: [{ text, checked }]. Anyone in the
        // conversation can tick/untick; the state is saved here for everyone.
        Schema::table('chat_messages', fn (Blueprint $t) => $t->json('checklist')->nullable()->after('body'));
    }

    public function down(): void
    {
        Schema::table('chat_messages', fn (Blueprint $t) => $t->dropColumn('checklist'));
    }
};
