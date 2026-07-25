<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "New" is retired as a Lead Quality — existing New leads become Qualified.
        DB::table('leads')->where('lead_status', 'new')->update(['lead_status' => 'qualified']);

        // New leads now default to Qualified instead of New.
        Schema::table('leads', function (Blueprint $table) {
            $table->string('lead_status')->default('qualified')->change();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('lead_status')->default('new')->change();
        });
    }
};
