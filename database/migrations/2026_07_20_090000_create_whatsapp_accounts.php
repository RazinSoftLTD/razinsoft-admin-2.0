<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');                              // e.g. Support, Tech, Project
            $table->string('color', 9)->default('#25d366');
            $table->string('session_key')->unique();             // gateway session id
            $table->string('session_state')->default('disconnected'); // qr|connecting|connected|disconnected
            $table->boolean('is_connected')->default(false);
            $table->string('display_number')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_account_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('whatsapp_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['account_id', 'user_id']);
        });

        Schema::table('whatsapp_chats', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('id')->index();
        });

        // --- Migrate the existing single number into the first account ---
        $settings = DB::table('whatsapp_settings')->first();
        $number = $settings->display_number ?? null;
        $accountId = DB::table('whatsapp_accounts')->insertGetId([
            'name' => $number ? 'Main' : 'Main',
            'color' => '#25d366',
            'session_key' => 'default', // gateway maps this to the current session dir
            'session_state' => $settings->session_state ?? 'disconnected',
            'is_connected' => $settings->is_connected ?? false,
            'display_number' => $number,
            'connected_at' => $settings->connected_at ?? null,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Give every panel user (admin + staff) access to the migrated account so nobody loses the inbox.
        $panelIds = DB::table('users')->whereIn('role', ['admin', 'staff'])->pluck('id');
        foreach ($panelIds as $uid) {
            DB::table('whatsapp_account_user')->insert(['account_id' => $accountId, 'user_id' => $uid]);
        }

        // Link all existing chats to the account.
        DB::table('whatsapp_chats')->update(['account_id' => $accountId]);
    }

    public function down(): void
    {
        Schema::table('whatsapp_chats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
        Schema::dropIfExists('whatsapp_account_user');
        Schema::dropIfExists('whatsapp_accounts');
    }
};
