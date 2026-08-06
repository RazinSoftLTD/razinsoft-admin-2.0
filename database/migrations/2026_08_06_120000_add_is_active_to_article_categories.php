<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_categories', function (Blueprint $table) {
            // Inactive means "not offered for new articles". Posts already filed under it keep
            // their category — retiring a category should not quietly re-file old work.
            $table->boolean('is_active')->default(true)->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('article_categories', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
