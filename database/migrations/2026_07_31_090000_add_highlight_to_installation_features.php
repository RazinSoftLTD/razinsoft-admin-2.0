<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lets the admin mark the features worth drawing the eye to in the comparison matrix. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installation_features', function (Blueprint $table) {
            $table->boolean('is_highlighted')->default(false)->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('installation_features', function (Blueprint $table) {
            $table->dropColumn('is_highlighted');
        });
    }
};
