<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            // Assigned sales person for this follow-up (defaults to the lead's owner).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('call');        // call | whatsapp | meeting | email | sms | other
            $table->string('priority')->default('medium');  // low | medium | high
            $table->text('note')->nullable();
            $table->dateTime('scheduled_at');
            $table->string('status')->default('pending');   // pending | done | cancelled (overdue is derived)
            $table->text('completion_note')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_follow_ups');
    }
};
