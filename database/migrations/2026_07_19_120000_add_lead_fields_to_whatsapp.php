<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_chats', function (Blueprint $table) {
            $table->string('lead_quality', 20)->nullable()->after('chat_type');   // qualified | unqualified
            $table->string('interested_product')->nullable()->after('lead_quality');
        });

        Schema::table('whatsapp_settings', function (Blueprint $table) {
            // Extra interest/product options an admin can add on top of the live product list.
            $table->text('interest_options')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_chats', function (Blueprint $table) {
            $table->dropColumn(['lead_quality', 'interested_product']);
        });
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->dropColumn('interest_options');
        });
    }
};
