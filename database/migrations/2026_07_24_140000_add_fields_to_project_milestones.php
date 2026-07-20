<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_milestones', function (Blueprint $table) {
            $table->string('priority', 20)->default('medium')->after('status');
            $table->string('color', 20)->nullable()->after('priority');   // label colour
            $table->string('icon', 30)->nullable()->after('color');       // icon key
        });
    }

    public function down(): void
    {
        Schema::table('project_milestones', fn (Blueprint $t) => $t->dropColumn(['priority', 'color', 'icon']));
    }
};
