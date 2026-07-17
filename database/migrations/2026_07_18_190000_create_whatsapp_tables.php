<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** WhatsApp Business Cloud API inbox — settings, chats, messages, notes, labels, quick replies. */
return new class extends Migration
{
    public function up(): void
    {
        // Singleton API config (managed in Settings › WhatsApp API).
        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number_id')->nullable();
            $table->string('business_account_id')->nullable();
            $table->text('access_token')->nullable();       // encrypted
            $table->string('app_secret')->nullable();       // encrypted — verifies webhook signatures
            $table->string('verify_token')->nullable();     // our own token echoed to Meta on webhook verify
            $table->string('api_version')->default('v21.0');
            $table->string('display_number')->nullable();   // set on successful test
            $table->boolean('is_connected')->default(false);
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 20)->default('#6366f1');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // One chat per WhatsApp contact (WhatsApp is 1:1).
        Schema::create('whatsapp_chats', function (Blueprint $table) {
            $table->id();
            $table->string('wa_id')->unique();              // phone in international format
            $table->string('name')->nullable();
            $table->string('profile_name')->nullable();
            $table->foreignId('client_id')->nullable();     // matched users.id (customer), if any
            $table->foreignId('assigned_to')->nullable();   // agent
            $table->string('status', 20)->default('open');  // open | pending | resolved
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 200)->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();
            $table->index(['status', 'last_message_at']);
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id');
            $table->string('wa_message_id')->nullable()->index();
            $table->string('direction', 4);                 // in | out
            $table->string('type', 20)->default('text');    // text | image | document | audio | video | sticker | location
            $table->text('body')->nullable();
            $table->string('media_path')->nullable();       // stored file (public disk)
            $table->string('media_mime')->nullable();
            $table->string('media_name')->nullable();
            $table->string('status', 12)->default('received'); // received | sent | delivered | read | failed
            $table->foreignId('agent_id')->nullable();      // who sent (outgoing)
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['chat_id', 'id']);
        });

        Schema::create('whatsapp_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id');
            $table->foreignId('user_id');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('whatsapp_chat_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id');
            $table->foreignId('label_id');
            $table->unique(['chat_id', 'label_id']);
        });

        Schema::create('whatsapp_quick_replies', function (Blueprint $table) {
            $table->id();
            $table->string('shortcut')->nullable();
            $table->text('body');
            $table->timestamps();
        });

        // Seed starter labels + quick replies + the settings row.
        foreach ([['VIP', '#a855f7'], ['Pending', '#f59e0b'], ['Paid', '#10b981'], ['Lead', '#3b82f6']] as $i => [$n, $c]) {
            DB::table('whatsapp_labels')->insert(['name' => $n, 'color' => $c, 'position' => $i, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([
            ['/hi', 'Hello! Thanks for reaching out to RazinSoft. How can we help you today?'],
            ['/price', 'You can view our product plans here: https://razinsoft.com/products'],
            ['/thanks', 'Thank you! Let us know if there is anything else we can help with.'],
        ] as [$s, $b]) {
            DB::table('whatsapp_quick_replies')->insert(['shortcut' => $s, 'body' => $b, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('whatsapp_settings')->insert(['verify_token' => \Illuminate\Support\Str::random(24), 'api_version' => 'v21.0', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        foreach (['whatsapp_quick_replies', 'whatsapp_chat_label', 'whatsapp_notes', 'whatsapp_messages', 'whatsapp_chats', 'whatsapp_labels', 'whatsapp_settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
