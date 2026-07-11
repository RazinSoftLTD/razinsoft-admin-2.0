<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_code')->nullable()->after('id');
            $table->string('salutation', 10)->nullable()->after('name');
            $table->foreignId('designation_id')->nullable()->after('job_title')->constrained('designations')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('designation_id')->constrained('departments')->nullOnDelete();
            $table->foreignId('reporting_to')->nullable()->after('department_id')->constrained('users')->nullOnDelete();
            $table->string('language', 10)->nullable()->default('en')->after('reporting_to');
            $table->date('joining_date')->nullable()->after('language');
            $table->date('date_of_birth')->nullable()->after('joining_date');
            $table->text('about')->nullable()->after('note');
            $table->string('employment_type')->nullable()->after('about');
            $table->date('probation_end_date')->nullable()->after('employment_type');
            $table->date('notice_start_date')->nullable()->after('probation_end_date');
            $table->date('notice_end_date')->nullable()->after('notice_start_date');
            $table->boolean('receive_email_notifications')->default(true)->after('notice_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designation_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('reporting_to');
            $table->dropColumn(['employee_code', 'salutation', 'language', 'joining_date', 'date_of_birth', 'about', 'employment_type', 'probation_end_date', 'notice_start_date', 'notice_end_date', 'receive_email_notifications']);
        });
        Schema::dropIfExists('departments');
        Schema::dropIfExists('designations');
    }
};
