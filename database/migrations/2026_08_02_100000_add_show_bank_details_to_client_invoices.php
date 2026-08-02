<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether an invoice carries the company's bank details.
 *
 * Not every invoice wants them: one paid by card through the pay link has no use for an account
 * number, while one settled by transfer needs it. Defaults to on so existing invoices keep showing
 * what the redesign gave them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->boolean('show_bank_details')->default(true)->after('terms');
        });
    }

    public function down(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->dropColumn('show_bank_details');
        });
    }
};
