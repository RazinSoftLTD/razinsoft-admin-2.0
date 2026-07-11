<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();          // body optional when there's an attachment
            $table->string('attachment')->nullable()->after('body');
            $table->string('attachment_name')->nullable()->after('attachment');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['attachment', 'attachment_name']);
        });
    }
};
