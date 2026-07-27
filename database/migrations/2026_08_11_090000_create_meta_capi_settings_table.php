<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings for Meta's Conversions API — the server-side half of the pixel.
 *
 * The browser pixel loses events to ad blockers, tracking prevention and people closing the tab
 * mid-request. Sending the same events from here recovers those, and matches better because the
 * server knows the customer's real email and phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_capi_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->string('pixel_id')->nullable();
            $table->text('access_token')->nullable();
            // Events Manager ▸ Test Events gives a code; while it is set, events are flagged as
            // test data and never affect real reporting.
            $table->string('test_event_code')->nullable();
            $table->string('api_version', 12)->default('v21.0');
            // Which events to send, so one noisy event can be switched off without a deploy.
            $table->json('events')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->string('last_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_capi_settings');
    }
};
