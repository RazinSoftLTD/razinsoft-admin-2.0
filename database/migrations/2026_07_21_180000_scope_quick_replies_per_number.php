<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Quick replies are now strictly per-number (no "shared/all numbers").
     * Attach any legacy shared rows (account_id NULL) to the first number so
     * they aren't orphaned — the admin can move or delete them afterwards.
     */
    public function up(): void
    {
        $firstAccountId = DB::table('whatsapp_accounts')->orderBy('position')->orderBy('id')->value('id');
        if ($firstAccountId) {
            DB::table('whatsapp_quick_replies')->whereNull('account_id')->update(['account_id' => $firstAccountId]);
        }
    }

    public function down(): void
    {
        // No-op: we can't tell which rows were originally shared.
    }
};
