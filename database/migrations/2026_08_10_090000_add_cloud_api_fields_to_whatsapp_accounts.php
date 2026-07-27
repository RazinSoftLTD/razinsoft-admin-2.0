<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lets each WhatsApp number choose its own transport.
 *
 * The driver used to be one setting for the whole installation, so QR numbers and a Meta Cloud API
 * number could not exist side by side — turning one on turned the others off. It belongs on the
 * account: a number is either a scanned WhatsApp Web session or a Cloud API number, and there is
 * no reason the answer has to be the same for all of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->string('driver', 20)->default('baileys')->after('name');
            $table->string('phone_number_id')->nullable()->after('session_state');
            $table->string('business_account_id')->nullable()->after('phone_number_id');
            $table->text('access_token')->nullable()->after('business_account_id');
            $table->text('app_secret')->nullable()->after('access_token');
            $table->string('verify_token')->nullable()->after('app_secret');
            $table->string('api_version', 12)->nullable()->after('verify_token');
        });

        $this->moveGlobalCloudApiAccountAcross();
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'driver', 'phone_number_id', 'business_account_id',
                'access_token', 'app_secret', 'verify_token', 'api_version',
            ]);
        });
    }

    /**
     * An installation already running on Cloud API has its credentials in the single settings row.
     * Give them an account of their own, so the number survives the driver becoming per-account.
     */
    private function moveGlobalCloudApiAccountAcross(): void
    {
        $settings = DB::table('whatsapp_settings')->first();

        if (! $settings || $settings->driver !== 'cloud_api' || ! $settings->phone_number_id) {
            return;
        }

        $exists = DB::table('whatsapp_accounts')->where('phone_number_id', $settings->phone_number_id)->exists();

        if ($exists) {
            return;
        }

        DB::table('whatsapp_accounts')->insert([
            'name' => $settings->display_number ?: 'Cloud API number',
            'driver' => 'cloud_api',
            'color' => '#25D366',
            'session_key' => 'cloud-'.Str::lower(Str::random(10)),
            'session_state' => $settings->is_connected ? 'connected' : 'disconnected',
            'is_connected' => (bool) $settings->is_connected,
            'display_number' => $settings->display_number,
            'connected_at' => $settings->connected_at,
            // Ciphertext moves as-is: both columns use the same `encrypted` cast and the same key.
            'phone_number_id' => $settings->phone_number_id,
            'business_account_id' => $settings->business_account_id,
            'access_token' => $settings->access_token,
            'app_secret' => $settings->app_secret,
            'verify_token' => $settings->verify_token,
            'api_version' => $settings->api_version ?: 'v21.0',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
