<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Optional shipping address on CRM invoices (set from the list Action menu). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('client_invoices', 'shipping_address')) {
                $table->text('shipping_address')->nullable()->after('bill_to_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('client_invoices', 'shipping_address')) {
                $table->dropColumn('shipping_address');
            }
        });
    }
};
