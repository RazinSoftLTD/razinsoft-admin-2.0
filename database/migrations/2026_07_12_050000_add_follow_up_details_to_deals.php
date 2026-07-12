<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->string('follow_up_title')->nullable()->after('next_follow_up_at');
            $table->text('follow_up_note')->nullable()->after('follow_up_title');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['follow_up_title', 'follow_up_note']);
        });
    }
};
