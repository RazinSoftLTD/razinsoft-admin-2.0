<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice configuration: unit types, tax/charge types, brand logo — all editable
 * from Settings → Invoice Configuration. Plus per-line unit / multiple taxes / attachment.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Unit types for line quantities (Items, Hours, Pcs, …).
        Schema::create('invoice_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Tax / charge types (Vat/Tax 5.5%, Paypal Charge 6%, …).
        Schema::create('invoice_taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 6, 3)->default(0); // percentage
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Single-row branding/config for invoices.
        Schema::create('invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->string('logo')->nullable();
            $table->string('brand_name')->nullable();
            $table->timestamps();
        });

        // Per-line extras on invoice items.
        Schema::table('client_invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('client_invoice_items', 'unit')) {
                $table->string('unit')->nullable()->after('qty');
            }
            if (! Schema::hasColumn('client_invoice_items', 'taxes')) {
                $table->json('taxes')->nullable()->after('tax_percent'); // [{name,rate}, …]
            }
            if (! Schema::hasColumn('client_invoice_items', 'attachment')) {
                $table->string('attachment')->nullable()->after('taxes');
            }
        });

        // sub_description now holds light HTML (bold/italic) — widen it on MySQL.
        // (SQLite has no enforced string length, so this is a no-op there.)
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE client_invoice_items MODIFY sub_description TEXT NULL');
        }

        // Seed sensible defaults.
        DB::table('invoice_units')->insert([
            ['name' => 'Items', 'is_default' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Hours', 'is_default' => false, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Pcs', 'is_default' => false, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('invoice_taxes')->insert([
            ['name' => 'Vat/Tax', 'rate' => 5.5, 'is_default' => false, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Paypal Charge', 'rate' => 6, 'is_default' => false, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('invoice_settings')->insert([
            ['brand_name' => 'RazinSoft', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::table('client_invoice_items', function (Blueprint $table) {
            foreach (['unit', 'taxes', 'attachment'] as $c) {
                if (Schema::hasColumn('client_invoice_items', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::dropIfExists('invoice_settings');
        Schema::dropIfExists('invoice_taxes');
        Schema::dropIfExists('invoice_units');
    }
};
