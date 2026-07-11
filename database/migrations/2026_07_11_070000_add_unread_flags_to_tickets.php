<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('unread_by_admin')->default(true)->after('customer_seen_at');
            $table->boolean('unread_by_customer')->default(false)->after('unread_by_admin');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['unread_by_admin', 'unread_by_customer']);
        });
    }
};
