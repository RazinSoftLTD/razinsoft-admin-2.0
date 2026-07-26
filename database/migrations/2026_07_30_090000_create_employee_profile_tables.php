<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backing tables for the employee profile tabs that had no home yet: uploaded documents,
 * salary/payroll records and the shift roster. Attendance, leave, tasks, timesheet,
 * tickets, permissions and activity all read from tables that already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category', 40)->default('other');   // contract | nid | certificate | other
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'category']);
        });

        Schema::create('employee_payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period');                              // the month being paid
            $table->decimal('basic', 14, 2)->default(0);
            $table->decimal('allowance', 14, 2)->default(0);
            $table->decimal('bonus', 14, 2)->default(0);
            $table->decimal('deduction', 14, 2)->default(0);
            $table->decimal('net_pay', 14, 2)->default(0);
            $table->string('currency', 8)->default('BDT');
            $table->string('status', 12)->default('pending');    // pending | paid
            $table->date('paid_on')->nullable();
            $table->string('method')->nullable();                // bank / cash / wallet
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'period']);               // one payslip per person per month
        });

        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('General');          // General / Morning / Night …
            $table->time('starts_at')->default('10:00:00');
            $table->time('ends_at')->default('19:00:00');
            $table->string('week_offs')->nullable();             // csv of 0..6 (Sun..Sat)
            $table->date('effective_from');
            $table->date('effective_to')->nullable();            // null = still current
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_shifts');
        Schema::dropIfExists('employee_payrolls');
        Schema::dropIfExists('employee_documents');
    }
};
