<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A headstone for a chat someone deleted on purpose. The phone keeps its own copy and
        // replays it on every resync; without this the wipe would quietly undo itself.
        Schema::create('whatsapp_deleted_chats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('wa_id');
            $table->string('phone')->nullable();
            $table->timestamp('deleted_at');
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'wa_id']);
            $table->index(['account_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_deleted_chats');
    }
};
