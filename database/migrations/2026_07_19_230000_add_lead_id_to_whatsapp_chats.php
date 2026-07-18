<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_chats', function (Blueprint $table) {
            $table->unsignedBigInteger('lead_id')->nullable()->after('client_id');
            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_chats', function (Blueprint $table) {
            $table->dropIndex(['lead_id']);
            $table->dropColumn('lead_id');
        });
    }
};
