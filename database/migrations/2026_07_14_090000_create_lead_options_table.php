<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable CRM lead taxonomies (Lead Sources, Lead Departments), managed
 * from Settings → CRM Settings. Seeded with the values that used to be hardcoded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_options', function (Blueprint $table) {
            $table->id();
            $table->string('type');          // 'source' | 'department'
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('type');
        });

        // Seed the current defaults so nothing changes for existing forms.
        $now = now();
        $rows = [];
        foreach (['WhatsApp', 'Website', 'Facebook', 'LinkedIn', 'Email', 'Others'] as $i => $label) {
            $rows[] = ['type' => 'source', 'label' => $label, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (['Sales', 'Support', 'Development', 'Marketing'] as $i => $label) {
            $rows[] = ['type' => 'department', 'label' => $label, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('lead_options')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_options');
    }
};
