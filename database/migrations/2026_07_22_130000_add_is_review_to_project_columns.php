<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_columns', function (Blueprint $table) {
            // Tasks sitting in a review column count as "Pending Approvals" on the overview.
            $table->boolean('is_review')->default(false)->after('is_done');
        });
    }

    public function down(): void
    {
        Schema::table('project_columns', fn (Blueprint $t) => $t->dropColumn('is_review'));
    }
};
