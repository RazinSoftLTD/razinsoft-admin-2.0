<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved billing addresses for a customer. A customer keeps several and marks one default, so
 * checkout can offer "use the saved one" instead of asking for it again on every order.
 *
 * The address already on `users` is copied in as each customer's first, default entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 20)->default('other');  // home | office | other
            $table->string('full_name')->nullable();
            $table->string('company')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address');
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('country');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_default']);
        });

        // Seed from what customers already have on their profile, so nobody starts with none.
        $customers = \Illuminate\Support\Facades\DB::table('users')
            ->where('role', 'customer')
            ->whereNotNull('address')
            ->whereNotNull('country')
            ->get(['id', 'name', 'company', 'phone', 'address', 'city', 'state', 'zip', 'country']);

        foreach ($customers as $c) {
            \Illuminate\Support\Facades\DB::table('billing_addresses')->insert([
                'user_id' => $c->id,
                // Nothing on the profile said what kind of address it is.
                'label' => 'other',
                'full_name' => $c->name,
                'company' => $c->company,
                'phone' => $c->phone,
                'address' => $c->address,
                'city' => $c->city,
                'state' => $c->state,
                'zip' => $c->zip,
                'country' => $c->country,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_addresses');
    }
};
