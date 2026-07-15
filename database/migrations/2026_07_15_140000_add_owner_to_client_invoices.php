<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner (managing staff) for an invoice → unlocks the full Owned / Added / Added & Owned / All
 * scope ladder, mirroring Clients. Owned = invoices for a client I manage; Added = invoices I created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('client_invoices', 'owner_id')) {
                $table->foreignId('owner_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });

        // Backfill existing invoices: owner = the client's account manager, else the creator.
        DB::table('client_invoices')->whereNull('owner_id')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $inv) {
                $ownerId = $inv->client_id
                    ? (DB::table('users')->where('id', $inv->client_id)->value('account_manager_id') ?: $inv->created_by)
                    : $inv->created_by;
                DB::table('client_invoices')->where('id', $inv->id)->update(['owner_id' => $ownerId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('client_invoices', 'owner_id')) {
                $table->dropConstrainedForeignId('owner_id');
            }
        });
    }
};
