<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add-to-cart events from the website.
 *
 * The cart itself lives in the browser (localStorage), so until now nothing about it reached the
 * server and "who is putting products in their cart" was unanswerable. Each add is beaconed here,
 * for signed-in clients and anonymous visitors alike — the latter identified only by IP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_events', function (Blueprint $table) {
            $table->id();
            // Null for a visitor who is not signed in; the row is still worth keeping.
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_slug')->nullable();
            $table->string('product_name');                   // snapshot — survives a renamed product
            $table->string('label')->nullable();              // "Regular License", plan name, …
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->unsignedInteger('qty')->default(1);
            $table->string('country')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('client_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_events');
    }
};
