<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Collapse the old pipeline states into just quality: new / qualified / unqualified.
        DB::table('leads')->whereIn('lead_status', ['proposal', 'negotiation', 'won'])->update(['lead_status' => 'qualified']);
        DB::table('leads')->where('lead_status', 'lost')->update(['lead_status' => 'unqualified']);
        DB::table('leads')->where('lead_status', 'contacted')->update(['lead_status' => 'new']);
    }

    public function down(): void
    {
        // One-way simplification — nothing to restore.
    }
};
