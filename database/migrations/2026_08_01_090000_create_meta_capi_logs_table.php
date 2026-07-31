<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per event handed to Meta. The settings row only ever held the last result, which
        // answers "is it working right now" and nothing about what was actually sent, or when a
        // particular lead was reported.
        Schema::create('meta_capi_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event')->index();              // Purchase, QualifiedLead…
            $table->string('event_id')->index();           // the dedup key, so a row can be traced
            $table->string('status', 12)->index();          // sent | failed
            $table->text('error')->nullable();
            // What it was about, in words, so the log reads without opening anything else.
            $table->string('subject')->nullable();
            $table->string('source')->nullable();           // WhatsApp, Website…
            $table->boolean('backfilled')->default(false)->index();
            $table->timestamp('sent_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_capi_logs');
    }
};
