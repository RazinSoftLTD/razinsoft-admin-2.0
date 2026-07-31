<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The day's first reading, kept alongside the latest one.
 *
 * A day's sales used to be readable only as the gap between two days' rows, so
 * "sold today" could not be answered until tomorrow. Recording where the day
 * started — written once, when the row is created, and never touched again —
 * lets today's figure move as sales land, hours before the day closes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('envato_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('opening_sales')->nullable()->after('number_of_sales');
        });

        // Rows already written today have no recorded opening. Seeding it with the
        // current total is the only honest choice: we know what the number is now,
        // not what it was at midnight, so today counts from this point forward.
        DB::table('envato_snapshots')
            ->whereNull('opening_sales')
            ->update(['opening_sales' => DB::raw('number_of_sales')]);
    }

    public function down(): void
    {
        Schema::table('envato_snapshots', function (Blueprint $table) {
            $table->dropColumn('opening_sales');
        });
    }
};
