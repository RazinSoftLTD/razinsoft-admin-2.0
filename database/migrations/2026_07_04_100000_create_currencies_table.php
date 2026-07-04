<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();   // ISO code, e.g. USD
            $table->string('symbol', 12);           // display symbol, e.g. $
            $table->string('name')->nullable();     // e.g. US Dollar
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed the currencies that were previously hard-coded so nothing breaks.
        $seed = [
            ['USD', '$', 'US Dollar'], ['BDT', '৳', 'Bangladeshi Taka'], ['EUR', '€', 'Euro'],
            ['GBP', '£', 'Pound Sterling'], ['INR', '₹', 'Indian Rupee'], ['AUD', 'A$', 'Australian Dollar'],
            ['CAD', 'C$', 'Canadian Dollar'], ['AED', 'د.إ', 'UAE Dirham'], ['SGD', 'S$', 'Singapore Dollar'],
            ['MYR', 'RM', 'Malaysian Ringgit'], ['SAR', '﷼', 'Saudi Riyal'], ['JPY', '¥', 'Japanese Yen'],
        ];
        $now = now();
        \DB::table('currencies')->insert(collect($seed)->map(fn ($c, $i) => [
            'code' => $c[0], 'symbol' => $c[1], 'name' => $c[2],
            'is_active' => true, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
