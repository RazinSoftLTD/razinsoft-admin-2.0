<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singleton config — the Envato personal token lives here (encrypted).
        Schema::create('envato_settings', function (Blueprint $table) {
            $table->id();
            $table->text('personal_token')->nullable();
            $table->boolean('is_connected')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_as')->nullable();     // username the token belongs to
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('auto_sync')->default(true);
            $table->timestamps();
        });

        // Authors we watch (ours + competitors).
        Schema::create('envato_authors', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->boolean('is_own')->default(false);      // "our" account vs a competitor
            $table->string('country')->nullable();
            $table->string('image')->nullable();
            $table->unsignedBigInteger('total_sales')->nullable();
            $table->unsignedInteger('followers')->nullable();
            $table->unsignedInteger('items_count')->nullable();
            $table->json('badges')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // A niche groups comparable products (e.g. "eCommerce") across authors.
        Schema::create('envato_niches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 20)->default('#6366f1');
            $table->timestamps();
        });

        // One tracked CodeCanyon item. Values are the latest snapshot from the API.
        Schema::create('envato_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id')->unique();          // Envato item id
            $table->foreignId('envato_author_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('envato_niche_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('author_username')->nullable();
            $table->string('url')->nullable();
            $table->string('site')->nullable();
            $table->string('classification')->nullable();             // category path
            $table->string('thumbnail_url')->nullable();
            $table->unsignedBigInteger('number_of_sales')->default(0);
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('price_cents')->default(0);
            $table->boolean('trending')->default(false);
            $table->json('tags')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('item_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index(['envato_niche_id', 'number_of_sales']);
        });

        // Daily snapshot — the API has no sales history, so we build it ourselves.
        Schema::create('envato_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envato_product_id')->constrained()->cascadeOnDelete();
            $table->date('captured_on');
            $table->unsignedBigInteger('number_of_sales');
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('price_cents')->default(0);
            $table->timestamps();
            $table->unique(['envato_product_id', 'captured_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envato_snapshots');
        Schema::dropIfExists('envato_products');
        Schema::dropIfExists('envato_niches');
        Schema::dropIfExists('envato_authors');
        Schema::dropIfExists('envato_settings');
    }
};
