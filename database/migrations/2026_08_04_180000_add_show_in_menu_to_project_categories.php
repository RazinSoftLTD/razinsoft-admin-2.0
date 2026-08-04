<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_categories', function (Blueprint $table) {
            // Ticked, the category gets its own entry under Workspace › Projects. Off by default:
            // a sidebar that lists every category is no easier to read than no shortcut at all.
            $table->boolean('show_in_menu')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('project_categories', function (Blueprint $table) {
            $table->dropColumn('show_in_menu');
        });
    }
};
