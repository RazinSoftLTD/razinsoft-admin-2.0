<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Website page visits by logged-in clients — the client-side activity trail. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');                 // e.g. /products/crm-suite
            $table->string('title')->nullable();    // page title if sent
            $table->string('referrer', 1000)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_activity_logs');
    }
};
