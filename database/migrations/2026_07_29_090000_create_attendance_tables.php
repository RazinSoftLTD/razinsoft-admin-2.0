<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance — one row per employee per day, filled by whichever method got there first
 * (biometric device, web check-in, first login, mobile or a manual HR entry). The unique
 * (user_id, work_date) index is what stops two methods creating duplicate attendance.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Single settings row for the whole HR module.
        Schema::create('hr_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('attendance_enabled')->default(true);
            $table->boolean('biometric_enabled')->default(false);
            $table->boolean('web_enabled')->default(true);
            $table->boolean('login_attendance_enabled')->default(false);
            $table->boolean('mobile_enabled')->default(false);
            $table->boolean('manual_enabled')->default(true);

            $table->time('office_start')->default('10:00:00');
            $table->time('office_end')->default('19:00:00');
            $table->unsignedSmallInteger('grace_minutes')->default(15);      // late after this
            $table->unsignedSmallInteger('min_work_minutes')->default(480);  // a full day
            $table->unsignedSmallInteger('half_day_minutes')->default(240);
            $table->boolean('overtime_enabled')->default(false);
            $table->unsignedSmallInteger('overtime_after_minutes')->default(540);
            $table->timestamps();
        });

        // Biometric readers (ZKTeco and friends).
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');                               // "Main Gate"
            $table->string('device_id')->nullable();              // serial / SN the device reports
            $table->string('ip_address')->nullable();
            $table->unsignedInteger('port')->default(4370);
            $table->string('brand')->default('ZKTeco');
            $table->string('api_token', 64)->nullable()->unique(); // lets a bridge push logs in
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Which biometric enrolment id belongs to which employee.
        Schema::table('users', function (Blueprint $table) {
            $table->string('biometric_id', 40)->nullable()->after('employee_code');
            $table->index('biometric_id');
        });

        // The day's attendance for one employee.
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');

            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            // biometric | web | web_login | mobile | manual
            $table->string('check_in_method', 12)->nullable();
            $table->string('check_out_method', 12)->nullable();

            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            // present | late | half_day | absent
            $table->string('status', 12)->default('present');

            $table->text('notes')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);             // one attendance per person per day
            $table->index('work_date');
        });

        // Every raw punch/event that fed the day's attendance — the audit trail.
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('attendance_devices')->nullOnDelete();

            $table->string('method', 12);                         // biometric | web | web_login | mobile | manual
            $table->string('direction', 4)->nullable();           // in | out
            $table->dateTime('punched_at');

            $table->string('biometric_id', 40)->nullable();       // as reported, before matching a user
            $table->string('ip_address', 45)->nullable();
            $table->string('device_type', 20)->nullable();        // desktop | mobile | tablet
            $table->string('browser')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('raw')->nullable();                      // untouched device payload
            $table->timestamps();

            $table->index(['user_id', 'punched_at']);
            $table->index('method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('attendances');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['biometric_id']);
            $table->dropColumn('biometric_id');
        });
        Schema::dropIfExists('attendance_devices');
        Schema::dropIfExists('hr_settings');
    }
};
