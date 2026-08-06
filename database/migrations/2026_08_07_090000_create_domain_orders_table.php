<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('domain');
            $table->unsignedTinyInteger('years')->default(1);
            $table->decimal('price', 10, 2);
            $table->decimal('renew_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');

            // pending → paid → registered, or action_needed when money is taken and the
            // registration then fails — the one state that must never be silent.
            $table->string('status')->default('pending');

            // The registrant, as ICANN wants them recorded. Kept on the order rather than the
            // user: the buyer and the legal registrant are often different people.
            $table->string('registrant_name');
            $table->string('registrant_company')->nullable();
            $table->string('registrant_email');
            $table->string('registrant_phone', 32);
            $table->string('registrant_address');
            $table->string('registrant_city');
            $table->string('registrant_country', 2);
            $table->string('registrant_zip', 20);

            $table->string('payment_gateway')->nullable();
            $table->string('gateway_session_id')->nullable();
            $table->timestamp('paid_at')->nullable();

            // What ResellerClub gave us back, for support and for the admin to act on.
            $table->string('rc_customer_id')->nullable();
            $table->string('rc_contact_id')->nullable();
            $table->string('rc_order_id')->nullable();
            $table->text('registration_error')->nullable();
            $table->timestamp('registered_at')->nullable();

            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_orders');
    }
};
