<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each person's light/dark choice, kept on the account.
 *
 * It was in localStorage, which meant it was forgotten on every other machine someone signed in
 * from. Stored here it follows them, and the server can render the right theme on the first byte
 * rather than letting the page flash light and swap.
 *
 * Defaults to `system`: the operating system already knows, and asking again is a worse first
 * impression than simply matching it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme', 10)->default('system')->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
