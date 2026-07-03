<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CRM billing invoices (separate from the order-fulfilment `invoices` table).
        Schema::create('client_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();

            // Billing snapshot (frozen at creation so later client edits don't rewrite history).
            $table->string('bill_to_name')->nullable();
            $table->string('bill_to_company')->nullable();
            $table->string('bill_to_email')->nullable();
            $table->string('bill_to_phone')->nullable();
            $table->text('bill_to_address')->nullable();

            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 8)->default('USD');
            $table->string('status')->default('draft'); // draft/sent/partially_paid/paid/overdue

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0); // maintained by payments (C5)

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('attachment')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('client_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_invoice_id')->constrained('client_invoices')->cascadeOnDelete();
            $table->string('description');
            $table->string('sub_description')->nullable();
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_invoice_items');
        Schema::dropIfExists('client_invoices');
    }
};
