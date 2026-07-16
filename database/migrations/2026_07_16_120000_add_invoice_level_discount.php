<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            // Invoice-level discount: 'flat' (fixed amount) or 'percent' (of the item net).
            $table->string('discount_type', 10)->nullable()->after('subtotal');
            $table->decimal('discount_value', 12, 2)->default(0)->after('discount_type');
        });
    }

    public function down(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value']);
        });
    }
};
