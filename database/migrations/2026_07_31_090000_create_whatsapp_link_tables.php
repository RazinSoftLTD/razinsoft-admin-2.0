<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A shareable WhatsApp link. The point of storing it rather than handing out a wa.me URL is
        // the click count: wa.me goes straight to WhatsApp and we never hear about it, so the link
        // we publish has to pass through here first.
        Schema::create('whatsapp_links', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();        // what appears in the short URL
            $table->string('label')->nullable();         // "Facebook ad — August", so a report reads
            $table->string('number', 32);                // full international form
            $table->text('message')->nullable();         // prefilled first message
            $table->boolean('is_active')->default(true); // retire a link without losing its history
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('whatsapp_link_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('whatsapp_links')->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('country')->nullable()->index();
            $table->string('referrer', 512)->nullable();  // which post or page sent them
            $table->string('device', 20)->nullable();     // mobile | desktop
            $table->text('user_agent')->nullable();
            // Every report on this table groups by time, so the index is the whole performance story.
            $table->timestamp('clicked_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_link_clicks');
        Schema::dropIfExists('whatsapp_links');
    }
};
