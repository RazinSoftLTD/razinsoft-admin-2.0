<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_quick_replies', function (Blueprint $table) {
            // Which WhatsApp number this quick reply belongs to. NULL = shared across all numbers.
            $table->foreignId('account_id')->nullable()->after('id')
                ->constrained('whatsapp_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_quick_replies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
    }
};
