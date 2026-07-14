<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Seed the default configurable deal pipeline stages (Settings → CRM Settings). */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('lead_options')->where('type', 'deal_stage')->exists()) {
            return;
        }
        $now = now();
        $rows = [];
        foreach (['New', 'Qualified', 'Proposal', 'Negotiation'] as $i => $label) {
            $rows[] = ['type' => 'deal_stage', 'label' => $label, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('lead_options')->insert($rows);
    }

    public function down(): void
    {
        DB::table('lead_options')->where('type', 'deal_stage')->delete();
    }
};
