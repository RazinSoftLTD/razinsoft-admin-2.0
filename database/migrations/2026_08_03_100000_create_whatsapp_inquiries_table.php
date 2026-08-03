<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every WhatsApp enquiry, recorded before anyone decides whether it is a lead.
 *
 * Leads only ever showed the enquiries worth keeping, which made the ad spend impossible to judge:
 * a number that produces fifty conversations and two leads looks identical to one that produces two
 * conversations and two leads. This table holds the whole top of the funnel — including the ones
 * that went nowhere — so the difference is visible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_inquiries', function (Blueprint $table) {
            $table->id();
            $table->date('inquiry_date')->index();
            $table->string('client_number', 32)->index();
            $table->string('client_name')->nullable();

            // Which of our numbers was contacted. Kept as a nullable reference plus a text copy:
            // the reference drives the reports, the copy survives an account being removed.
            $table->foreignId('whatsapp_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company_number', 32)->nullable();

            $table->boolean('conversation_started')->default(false)->index();
            $table->boolean('is_relevant')->default(false)->index();
            $table->string('interest')->nullable()->index();
            $table->text('remarks')->nullable();

            // Set once the enquiry has been promoted, so it is never counted twice.
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('converted_at')->nullable();

            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // The daily report groups by date and number; the list filters on the same pair.
            $table->index(['inquiry_date', 'whatsapp_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_inquiries');
    }
};
