<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Password history for client accounts — lets a super admin review the credentials
 * they set/generated for a client (stored encrypted at rest, not as plaintext).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('password'); // encrypted via model cast
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_password_histories');
    }
};
