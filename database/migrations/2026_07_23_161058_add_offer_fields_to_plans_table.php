<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('offer_type')->nullable()->after('price'); // percent|flat
            $table->decimal('offer_value', 10, 2)->nullable()->after('offer_type');
            $table->timestamp('offer_starts_at')->nullable()->after('offer_value');
            $table->timestamp('offer_ends_at')->nullable()->after('offer_starts_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['offer_type', 'offer_value', 'offer_starts_at', 'offer_ends_at']);
        });
    }
};
