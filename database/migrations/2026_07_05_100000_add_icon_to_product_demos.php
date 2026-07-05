<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_demos', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('type'); // uploaded icon image; falls back to the type preset
        });
    }

    public function down(): void
    {
        Schema::table('product_demos', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
