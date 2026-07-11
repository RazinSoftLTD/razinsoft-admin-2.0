<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A booked consultation slot (from the public website Book-a-Meeting flow).
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->text('notes')->nullable();               // what the client wants to discuss
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('meeting_link')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->unique(['date', 'start_time']);          // one meeting per slot
            $table->index(['date', 'status']);
        });

        // Single-row configuration for the booking calendar.
        Schema::create('booking_settings', function (Blueprint $table) {
            $table->id();
            $table->time('start_time')->default('10:00:00');       // office opens
            $table->time('end_time')->default('18:00:00');         // office closes
            $table->unsignedSmallInteger('slot_minutes')->default(120); // 2-hour slots
            $table->json('working_days')->nullable();              // 0=Sun … 6=Sat  (default Sun–Thu)
            $table->unsignedSmallInteger('advance_days')->default(30);  // how far ahead bookable
            $table->unsignedSmallInteger('lead_hours')->default(2);     // min notice before a slot
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('default_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('booking_settings');
    }
};
