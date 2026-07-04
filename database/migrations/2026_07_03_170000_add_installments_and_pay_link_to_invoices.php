<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            // Payment request: the specific amount the client is asked to pay now (null = full due).
            $table->decimal('requested_amount', 12, 2)->nullable()->after('amount_paid');
            // Public, unguessable token for the client pay link (no login needed).
            $table->string('public_token', 40)->nullable()->unique()->after('requested_amount');
        });

        // Backfill tokens for any existing invoices.
        foreach (\App\Models\ClientInvoice::whereNull('public_token')->pluck('id') as $id) {
            \App\Models\ClientInvoice::where('id', $id)->update(['public_token' => Str::random(40)]);
        }

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

    public function down(): void
    {
        Schema::dropIfExists('invoice_installments');
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->dropColumn(['requested_amount', 'public_token']);
        });
    }
};
