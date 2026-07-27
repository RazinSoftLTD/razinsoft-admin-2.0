<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the old single-account Email Settings table.
 *
 * Its screen, model and mailer are gone — every message now goes through the email module. What is
 * left is the row itself, which on a live installation holds the only copy of the SMTP password.
 * So the account is copied across first if it has not been already; dropping the table must not be
 * the thing that loses it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_settings')) {
            return;
        }

        $this->carryAccountOver();

        Schema::drop('email_settings');
    }

    /**
     * Nothing here can be recovered — the credentials live in email_configs now. Recreating the
     * table empty is enough to let the migration be rolled back.
     */
    public function down(): void
    {
        Schema::create('email_settings', function (Blueprint $table) {
            $table->id();
            $table->string('mailer')->default('smtp');
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('encryption')->nullable();
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });
    }

    private function carryAccountOver(): void
    {
        $old = DB::table('email_settings')->first();

        if (! $old || ! $old->host || ! $old->username) {
            return;
        }

        $exists = DB::table('email_configs')
            ->where('host', $old->host)->where('username', $old->username)->exists();

        if ($exists) {
            return;
        }

        // Stored with Laravel's `encrypted` cast, so it has to be decrypted here and re-encrypted
        // on the way in. An undecryptable value means a changed APP_KEY — the password is already
        // lost at that point, and refusing to migrate would not bring it back.
        try {
            $password = $old->password ? Crypt::decryptString($old->password) : null;
        } catch (\Throwable $e) {
            $password = null;
        }

        DB::table('email_configs')->insert([
            'name' => $old->from_name ?: 'Primary SMTP',
            'provider' => 'custom',
            'host' => $old->host,
            'port' => (int) ($old->port ?: 587),
            'encryption' => $old->encryption ?: 'tls',
            'username' => $old->username,
            'password' => $password ? Crypt::encryptString($password) : null,
            'from_email' => $old->from_address ?: $old->username,
            'from_name' => $old->from_name ?: config('app.name'),
            // Only account in means it has to be the default, or picking one still finds nothing.
            'is_default' => ! DB::table('email_configs')->exists(),
            'is_active' => true,
            'priority' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
