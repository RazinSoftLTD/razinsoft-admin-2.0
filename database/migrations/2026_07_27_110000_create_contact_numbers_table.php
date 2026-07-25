<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every phone number a lead or a client owns, one row each. `e164` is the normalized
 * (+countrycode + national number) form and is what lead ↔ client matching joins on —
 * so "+880 1877-987557" and "01877987557" resolve to the same contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_numbers', function (Blueprint $table) {
            $table->id();
            $table->morphs('contactable');                       // Lead or User (client)
            $table->string('label', 30)->default('mobile');      // mobile / office / whatsapp / other
            $table->string('dial_code', 8)->nullable();
            $table->string('number', 40);                        // as typed, for display
            $table->string('e164', 32)->nullable();              // +8801877987557 — null when unparseable
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_whatsapp')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index('e164');                               // the matching key
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_numbers');
    }
};
