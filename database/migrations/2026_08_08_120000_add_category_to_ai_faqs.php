<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_faqs', function (Blueprint $table) {
            // Free-text grouping — "Ready eCommerce", "Pricing", "Support" — so the shelf reads
            // as sections rather than one long pile once it grows past a screen.
            $table->string('category', 100)->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_faqs', fn (Blueprint $t) => $t->dropColumn('category'));
    }
};
