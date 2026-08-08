<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The website chat: conversations with visitors on razinsoft.com.
     *
     * Kept apart from the WhatsApp tables and from the team's own chat. A visitor is not a
     * WhatsApp number and not a colleague — they arrive without a name, on a particular page,
     * and may never come back. What they share with the others is the shape of a conversation,
     * which is why the panel can read them all the same way.
     */
    public function up(): void
    {
        Schema::create('site_chats', function (Blueprint $table) {
            $table->id();
            // The browser's own key, kept in localStorage — how a returning visitor finds their
            // conversation again without an account.
            $table->uuid('token')->unique();

            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();   // matched to a known client

            $table->string('status')->default('open');             // open | pending | resolved
            $table->unsignedBigInteger('assigned_to')->nullable();

            $table->string('page_url')->nullable();                // where they opened it
            $table->string('referrer')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('user_agent')->nullable();

            $table->text('last_message_preview')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('ai_handover_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_message_at']);
            $table->index('client_id');
        });

        Schema::create('site_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_id');
            $table->string('direction');                            // in (visitor) | out (us)
            $table->string('type')->default('text');                // text | file | option
            $table->text('body')->nullable();
            $table->string('attachment')->nullable();
            $table->string('attachment_name')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();     // the panel user who replied
            $table->boolean('ai_generated')->default(false);
            $table->string('ai_source')->nullable();                // faq | openai | handover | option
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['chat_id', 'id']);
        });

        // The buttons the widget offers before anyone types — "Ask about our products", and so on.
        Schema::create('site_chat_options', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('emoji', 16)->nullable();
            $table->text('reply')->nullable();                      // what we answer when tapped
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('taps')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_chat_options');
        Schema::dropIfExists('site_chat_messages');
        Schema::dropIfExists('site_chats');
    }
};
