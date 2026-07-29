<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One crawl of one site. Kept after it finishes so you can see where an address came from
        // and re-run the same target later.
        Schema::create('email_scrape_runs', function (Blueprint $table) {
            $table->id();
            $table->string('url');                                   // what was submitted
            $table->string('domain')->index();                       // host, for grouping runs
            $table->unsignedSmallInteger('max_pages')->default(25);
            $table->string('status')->default('pending')->index();   // pending|running|done|failed
            $table->unsignedSmallInteger('pages_crawled')->default(0);
            $table->unsignedInteger('emails_found')->default(0);     // includes duplicates skipped
            $table->unsignedInteger('emails_new')->default(0);       // actually stored
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('scraped_emails', function (Blueprint $table) {
            $table->id();
            // Unique on the address itself: the same address turning up on three sites is still one
            // person to mail, so the first sighting wins and later ones only bump last_seen_at.
            $table->string('email')->unique();
            $table->string('domain')->nullable()->index();           // the site it was found on
            $table->string('source_url', 1024)->nullable();          // the exact page
            $table->string('name')->nullable();                      // only when the page offers one
            $table->boolean('is_role_address')->default(false)->index(); // info@, sales@, no-reply@…
            $table->foreignId('run_id')->nullable()->constrained('email_scrape_runs')->nullOnDelete();
            $table->foreignId('imported_client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraped_emails');
        Schema::dropIfExists('email_scrape_runs');
    }
};
