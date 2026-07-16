<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            // Optional short description for a partial-payment request — shown on the pay link
            // and recorded as the payment's Remark once the client pays.
            $table->string('requested_note')->nullable()->after('requested_amount');
        });
    }

    public function down(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->dropColumn('requested_note');
        });
    }
};
