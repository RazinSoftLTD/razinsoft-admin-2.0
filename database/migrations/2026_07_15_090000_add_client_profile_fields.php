<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Extra client (customer) profile fields for the redesigned Add/Edit Client form. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = [
                'gender', 'website', 'tax_name', 'gst_number',
                'office_phone', 'client_category', 'client_sub_category',
            ];
            foreach ($cols as $c) {
                if (! Schema::hasColumn('users', $c)) {
                    $table->string($c)->nullable();
                }
            }
            if (! Schema::hasColumn('users', 'shipping_address')) {
                $table->text('shipping_address')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['gender', 'website', 'tax_name', 'gst_number', 'office_phone', 'client_category', 'client_sub_category', 'shipping_address'] as $c) {
                if (Schema::hasColumn('users', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
