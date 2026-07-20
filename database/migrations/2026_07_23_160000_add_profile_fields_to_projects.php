<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Short one-liner shown under the project name (distinct from the long `summary`).
            $table->string('subtitle')->nullable()->after('name');
            $table->string('avatar')->nullable()->after('subtitle');
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn(['subtitle', 'avatar']));
    }
};
