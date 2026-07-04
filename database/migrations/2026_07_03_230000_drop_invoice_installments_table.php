<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Installment plans were dropped per requirement — the flow is now a single
// validated "amount to receive" per online payment.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('invoice_installments');
    }

    public function down(): void
    {
        Schema::create('invoice_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_invoice_id')->constrained('client_invoices')->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
