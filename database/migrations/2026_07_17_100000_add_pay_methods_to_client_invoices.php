<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            // Payment gateways the client may use on the pay link (null → ['stripe']).
            $table->json('pay_methods')->nullable()->after('requested_amount');
        });
    }

    public function down(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->dropColumn('pay_methods');
        });
    }
};
