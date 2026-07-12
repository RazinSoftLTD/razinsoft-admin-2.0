<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('salutation')->nullable()->after('id');
            $table->string('dial_code')->nullable()->after('phone');
            $table->boolean('is_whatsapp')->default(false)->after('dial_code');
            $table->string('mobile')->nullable()->after('is_whatsapp');
            $table->string('office_phone')->nullable()->after('mobile');
            $table->foreignId('added_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('added_by');
            $table->dropColumn(['salutation', 'dial_code', 'is_whatsapp', 'mobile', 'office_phone']);
        });
    }
};
