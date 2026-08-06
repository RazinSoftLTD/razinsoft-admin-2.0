<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // A domain line has no product. Everything downstream that assumed one — licences,
            // downloads — skips on domain_order_id instead.
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreignId('domain_order_id')->nullable()->after('installation_plan_id')->constrained();
        });

        Schema::table('domain_orders', function (Blueprint $table) {
            // The cart order that paid for this domain. Payment state lives there now; the
            // domain_orders payment columns stay for the rows that predate the cart flow.
            $table->foreignId('order_id')->nullable()->after('user_id')->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('domain_order_id');
        });
        Schema::table('domain_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
