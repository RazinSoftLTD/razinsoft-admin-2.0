<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scraped_numbers', function (Blueprint $table) {
            $table->id();
            // Stored in full international form (+8801711257498) — the same number written three
            // ways on one site is one number to message, so that normalised form is the key.
            $table->string('number', 32)->unique();
            $table->string('raw', 64)->nullable();               // how the site wrote it
            // A wa.me / whatsapp:// link is the site telling us the number is on WhatsApp. A tel:
            // link or plain text is only a phone number, which may or may not be. Worth keeping
            // apart: messaging a landline through the gateway just burns the attempt.
            $table->boolean('is_whatsapp')->default(false)->index();
            $table->string('domain')->nullable()->index();
            $table->string('source_url', 1024)->nullable();
            $table->string('name')->nullable();
            $table->foreignId('run_id')->nullable()->constrained('email_scrape_runs')->nullOnDelete();
            $table->foreignId('imported_client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::table('email_scrape_runs', function (Blueprint $table) {
            $table->unsignedInteger('numbers_found')->default(0)->after('emails_new');
            $table->unsignedInteger('numbers_new')->default(0)->after('numbers_found');
        });
    }

    public function down(): void
    {
        Schema::table('email_scrape_runs', function (Blueprint $table) {
            $table->dropColumn(['numbers_found', 'numbers_new']);
        });
        Schema::dropIfExists('scraped_numbers');
    }
};
