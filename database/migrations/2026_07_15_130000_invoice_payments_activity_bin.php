<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Richer payments (project, currency, exchange rate, gateway, bank account, receipt),
 * a per-invoice activity log, and soft-delete so deleted invoices go to a recoverable Bin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            foreach ([
                'project_id' => fn () => $table->foreignId('project_id')->nullable()->after('client_invoice_id'),
                'currency' => fn () => $table->string('currency', 8)->nullable()->after('amount'),
                'exchange_rate' => fn () => $table->decimal('exchange_rate', 12, 4)->nullable()->after('currency'),
                'bank_account' => fn () => $table->string('bank_account')->nullable()->after('method'),
                'receipt' => fn () => $table->string('receipt')->nullable()->after('bank_account'),
            ] as $col => $add) {
                if (! Schema::hasColumn('invoice_payments', $col)) {
                    $add();
                }
            }
        });

        // Soft-delete invoices → recoverable Bin.
        Schema::table('client_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('client_invoices', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Per-invoice activity / audit log.
        Schema::create('invoice_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_invoice_id')->constrained('client_invoices')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor')->default('employee'); // employee | client | system
            $table->string('action');                     // created | payment_added | deleted | …
            $table->text('description')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_activities');
        Schema::table('client_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('client_invoices', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
        Schema::table('invoice_payments', function (Blueprint $table) {
            foreach (['project_id', 'currency', 'exchange_rate', 'bank_account', 'receipt'] as $c) {
                if (Schema::hasColumn('invoice_payments', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
