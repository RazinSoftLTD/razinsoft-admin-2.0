<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('items');                 // frozen line-item template
            $table->string('currency', 8)->default('USD');
            $table->string('interval')->default('monthly'); // weekly/monthly/quarterly/yearly
            $table->unsignedInteger('due_days')->default(14);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('payment_method')->nullable();
            $table->date('next_run_at');
            $table->date('last_run_at')->nullable();
            $table->unsignedInteger('generated_count')->default(0);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_invoices');
    }
};
