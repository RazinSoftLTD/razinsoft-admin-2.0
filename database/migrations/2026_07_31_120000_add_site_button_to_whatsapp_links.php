<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_links', function (Blueprint $table) {
            // Marks the one link the website's floating button uses. Only one is ever set — the
            // controller clears the others — so the site has a single, unambiguous answer.
            $table->boolean('is_site_button')->default(false)->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_links', function (Blueprint $table) {
            $table->dropColumn('is_site_button');
        });
    }
};
