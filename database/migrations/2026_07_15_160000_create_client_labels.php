<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client loyalty / priority labels (Regular, Gold, Platinum, …) — configured in
 * CRM Settings with a short description, and selectable when adding a client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('color', 20)->nullable(); // optional badge tint
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'client_label')) {
                $table->string('client_label')->nullable()->after('client_sub_category');
            }
        });

        // Seed the common loyalty tiers with a one-line meaning + badge colour.
        $now = now();
        DB::table('client_labels')->insert([
            ['name' => 'Regular', 'description' => 'Standard client — occasional or first-time business.', 'color' => 'gray', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Gold', 'description' => 'Valued repeat client with steady business.', 'color' => 'amber', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Platinum', 'description' => 'High-value client with significant, ongoing spend.', 'color' => 'slate', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Diamond', 'description' => 'Top-tier client — premium, long-term partnership.', 'color' => 'sky', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Elite', 'description' => 'Strategic key account — highest priority.', 'color' => 'violet', 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'client_label')) {
                $table->dropColumn('client_label');
            }
        });
        Schema::dropIfExists('client_labels');
    }
};
