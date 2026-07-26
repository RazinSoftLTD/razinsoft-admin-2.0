<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The inbox's free-text "Interested in" becomes the shared Product Category / Sub-category the
 * Lead, Deal and Client forms already use, so a WhatsApp contact converts into a lead carrying
 * the same values. `interested_product` stays for the rows recorded before this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_chats', function (Blueprint $table) {
            $table->string('product_category')->nullable()->after('interested_product');
            $table->string('product_sub_category')->nullable()->after('product_category');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_chats', function (Blueprint $table) {
            $table->dropColumn(['product_category', 'product_sub_category']);
        });
    }
};
