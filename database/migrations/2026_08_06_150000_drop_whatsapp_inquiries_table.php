<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WhatsApp Traffic is gone, so its table goes with it.
     *
     * Checked before writing this: the table was empty on production — nothing was ever recorded
     * through it and nothing was converted into a lead — so no history is lost here.
     */
    public function up(): void
    {
        Schema::dropIfExists('whatsapp_inquiries');
    }

    public function down(): void
    {
        Schema::create('whatsapp_inquiries', function (Blueprint $table) {
            $table->id();
            $table->date('inquiry_date');
            $table->string('client_number');
            $table->string('client_name')->nullable();
            $table->foreignId('whatsapp_account_id')->nullable();
            $table->string('company_number')->nullable();
            $table->boolean('conversation_started')->default(false);
            $table->boolean('is_relevant')->default(false);
            $table->string('interest')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('lead_id')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('added_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
