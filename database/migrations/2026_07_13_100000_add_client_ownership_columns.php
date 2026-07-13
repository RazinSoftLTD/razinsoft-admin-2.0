<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client ownership, so the clients permission can use the same scope ladder
 * (Owned / Added / Both) as Leads & Deals:
 *   - account_manager_id → the staff member the client is assigned to ("Owned")
 *   - created_by        → the staff member who added the client       ("Added")
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'account_manager_id')) {
                $table->foreignId('account_manager_id')->nullable()->after('reporting_to')->index();
            }
            if (! Schema::hasColumn('users', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('account_manager_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['account_manager_id', 'created_by'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
