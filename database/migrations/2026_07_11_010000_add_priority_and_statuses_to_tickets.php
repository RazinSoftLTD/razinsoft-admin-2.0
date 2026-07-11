<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('priority')->default('medium')->after('status');
        });

        // Statuses expanded to open / pending / resolved / closed — remap the old 'in_progress'.
        DB::table('tickets')->where('status', 'in_progress')->update(['status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
        DB::table('tickets')->whereIn('status', ['pending', 'resolved'])->update(['status' => 'in_progress']);
    }
};
