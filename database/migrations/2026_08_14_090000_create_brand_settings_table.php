<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The buyer's own branding, set from inside the panel.
 *
 * config/brand.php carries the shipped defaults; this row overrides them at runtime. Keeping them
 * apart matters: an update can ship a new default without overwriting what a customer has already
 * made theirs, and a customer never has to edit a file to rename the product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_settings', function (Blueprint $table) {
            $table->id();
            $table->string('product')->nullable();
            $table->string('tagline')->nullable();
            $table->string('logo')->nullable();   // stored path, wordmark for the sign-in screen
            $table->string('icon')->nullable();   // square mark for the sidebar and favicon
            $table->string('primary', 9)->nullable();
            $table->string('primary_hover', 9)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_settings');
    }
};
