<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance — internal money management for RazinSoft itself (not an accounting system).
 * Client billing stays in the Invoice module; Finance mirrors paid invoices as income and
 * reads unpaid ones as receivables.
 *
 * Wallets and bank accounts share one table so transfers, conversions and balances work
 * the same way for both; the `type` column is what the two menu pages filter on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10)->default('wallet');       // wallet | bank
            $table->string('name');                              // "Payoneer USD", "DBBL"
            $table->string('provider')->nullable();              // Payoneer / Wise / Stripe / DBBL …
            $table->string('currency', 8)->default('USD');
            $table->string('account_number')->nullable();        // bank account / IBAN, optional
            $table->decimal('opening_balance', 16, 2)->default(0);
            $table->decimal('current_balance', 16, 2)->default(0);
            $table->string('status', 12)->default('active');     // active | inactive
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
        });

        // Expense + income categories (Salary, Hosting, Facebook Ads …).
        Schema::create('finance_categories', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10)->default('expense');      // expense | income
            $table->string('name', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['kind', 'sort_order']);
        });

        // Every movement of money. Soft-deleted only — nothing is ever purged.
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            // income | expense | transfer | conversion | deposit | withdrawal | refund | adjustment
            $table->string('type', 16);
            $table->string('direction', 3);                      // in | out — what it does to the account
            $table->foreignId('account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
            $table->foreignId('counter_account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('finance_categories')->nullOnDelete();

            $table->decimal('amount', 16, 2)->default(0);        // in `currency`
            $table->string('currency', 8)->default('USD');
            $table->decimal('converted_amount', 16, 2)->nullable();   // conversions/transfers across currencies
            $table->decimal('exchange_rate', 16, 6)->nullable();
            $table->decimal('fee', 16, 2)->default(0);           // transfer / conversion fee
            $table->decimal('bank_charge', 16, 2)->default(0);

            $table->date('occurred_on');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('receipt')->nullable();

            // Where it came from: typed in by hand, or mirrored from an invoice payment.
            $table->string('source', 12)->default('manual');     // manual | invoice
            $table->foreignId('client_invoice_id')->nullable()->constrained('client_invoices')->nullOnDelete();
            $table->foreignId('invoice_payment_id')->nullable()->constrained('invoice_payments')->nullOnDelete();

            $table->uuid('transfer_group')->nullable();          // pairs the two legs of a transfer/conversion
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'occurred_on']);
            $table->index(['account_id', 'occurred_on']);
            $table->index('transfer_group');
        });

        // Bills we owe (hosting, subscriptions, suppliers, reimbursements).
        Schema::create('finance_payables', function (Blueprint $table) {
            $table->id();
            $table->string('vendor');
            $table->foreignId('category_id')->nullable()->constrained('finance_categories')->nullOnDelete();
            $table->decimal('amount', 16, 2)->default(0);
            $table->decimal('amount_paid', 16, 2)->default(0);
            $table->string('currency', 8)->default('USD');
            $table->date('bill_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 12)->default('unpaid');     // unpaid | partial | paid
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'due_date']);
        });

        // VAT / Tax / Withholding amounts to report and pay.
        Schema::create('finance_taxes', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16)->default('vat');          // vat | tax | withholding
            $table->string('title');
            $table->decimal('amount', 16, 2)->default(0);
            $table->string('currency', 8)->default('BDT');
            $table->date('period');                              // the month this belongs to
            $table->date('due_date')->nullable();
            $table->string('status', 12)->default('pending');    // pending | paid
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kind', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_taxes');
        Schema::dropIfExists('finance_payables');
        Schema::dropIfExists('finance_transactions');
        Schema::dropIfExists('finance_categories');
        Schema::dropIfExists('finance_accounts');
    }
};
