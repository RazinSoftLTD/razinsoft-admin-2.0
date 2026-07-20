<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installation_plans', function (Blueprint $table) {
            // Existing plans are already live, so they start as published.
            $table->string('status', 20)->default('published')->after('is_popular');
        });
    }

    public function down(): void
    {
        Schema::table('installation_plans', fn (Blueprint $t) => $t->dropColumn('status'));
    }
};
