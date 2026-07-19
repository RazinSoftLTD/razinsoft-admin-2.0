<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_members', function (Blueprint $table) {
            // Per-member access level for THIS project: view | tasks | manage.
            $table->string('access_level', 20)->default('manage')->after('user_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            // Does this project collect requirement files? (decided per project in its Settings.)
            $table->boolean('needs_requirements')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('project_members', fn (Blueprint $t) => $t->dropColumn('access_level'));
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn('needs_requirements'));
    }
};
