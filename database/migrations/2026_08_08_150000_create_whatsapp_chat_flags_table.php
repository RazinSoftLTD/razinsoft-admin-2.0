<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (chat, employee): what THIS person pinned, and what they left unread.
     *
     * Both used to be columns on the chat itself, so one person pinning a chat pinned it for the
     * whole team, and anyone opening it wiped the "unread" someone else had set to remind
     * themselves. A shared inbox still needs a private sense of what matters to you.
     */
    public function up(): void
    {
        Schema::create('whatsapp_chat_flags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('pinned_at')->nullable();
            $table->timestamp('unread_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_id', 'user_id']);
            $table->index(['user_id', 'pinned_at']);
        });

        // Carry the existing shared pins over to whoever can see them, so nobody's pinned chats
        // vanish on deploy. Panel users only — clients never see this inbox.
        $pinned = DB::table('whatsapp_chats')->whereNotNull('pinned_at')->pluck('pinned_at', 'id');
        if ($pinned->isEmpty()) {
            return;
        }

        $users = DB::table('users')->whereIn('role', ['admin', 'staff'])->pluck('id');
        $now = now();
        foreach ($pinned as $chatId => $pinnedAt) {
            $rows = $users->map(fn ($uid) => [
                'chat_id' => $chatId, 'user_id' => $uid,
                'pinned_at' => $pinnedAt, 'unread_at' => null,
                'created_at' => $now, 'updated_at' => $now,
            ])->all();
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('whatsapp_chat_flags')->insert($chunk);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_chat_flags');
    }
};
