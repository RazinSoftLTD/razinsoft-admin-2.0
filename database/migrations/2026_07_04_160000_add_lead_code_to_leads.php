<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('lead_code', 20)->nullable()->unique()->after('id');
        });

        // Per-year counter for LD-{yy}#### codes.
        Schema::create('lead_sequences', function (Blueprint $table) {
            $table->string('year', 4)->primary();
            $table->unsignedInteger('last_seq')->default(0);
        });

        // Backfill existing leads: a running per-year sequence in id order.
        $counters = [];
        foreach (DB::table('leads')->orderBy('id')->get(['id', 'created_at']) as $lead) {
            $year = \Illuminate\Support\Carbon::parse($lead->created_at)->format('Y');
            $yy = substr($year, 2);
            $counters[$year] = ($counters[$year] ?? 0) + 1;
            DB::table('leads')->where('id', $lead->id)->update([
                'lead_code' => sprintf('LD-%s%04d', $yy, $counters[$year]),
            ]);
        }

        // Seed the counter so new leads continue after the backfilled ones.
        foreach ($counters as $year => $seq) {
            DB::table('lead_sequences')->insert(['year' => $year, 'last_seq' => $seq]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_sequences');
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('lead_code');
        });
    }
};
