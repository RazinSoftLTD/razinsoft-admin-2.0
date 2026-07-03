<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('converted_client_id')->nullable()->after('priority')->constrained('users')->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('converted_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_client_id');
            $table->dropColumn('converted_at');
        });
    }
};
