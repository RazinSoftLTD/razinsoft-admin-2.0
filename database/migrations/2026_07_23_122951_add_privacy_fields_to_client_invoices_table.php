<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('created_by');
            $table->foreignId('made_private_by')->nullable()->after('is_private')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('made_private_by');
            $table->dropColumn('is_private');
        });
    }
};
