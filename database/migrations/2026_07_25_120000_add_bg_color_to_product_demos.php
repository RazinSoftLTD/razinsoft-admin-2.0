<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_demos', function (Blueprint $table) {
            // Per-card background colour for the "Try It Live" cards (null = website preset).
            $table->string('bg_color', 20)->nullable()->after('badge');
        });
    }

    public function down(): void
    {
        Schema::table('product_demos', fn (Blueprint $t) => $t->dropColumn('bg_color'));
    }
};
