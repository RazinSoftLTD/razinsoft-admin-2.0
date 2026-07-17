<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make the WhatsApp integration driver-swappable: 'baileys' (QR / WhatsApp Web via the Node
 * gateway, Phase 1) or 'cloud_api' (official Meta Cloud API, future). The provider layer reads
 * these — no business logic is tied to a specific transport.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->string('driver', 20)->default('baileys')->after('id');
            $table->string('gateway_url')->nullable()->after('driver');   // Node Baileys gateway base URL
            $table->text('gateway_secret')->nullable()->after('gateway_url'); // encrypted shared secret
            $table->string('session_state', 20)->default('disconnected')->after('is_connected'); // disconnected|qr|connecting|connected
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->dropColumn(['driver', 'gateway_url', 'gateway_secret', 'session_state']);
        });
    }
};
