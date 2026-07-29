<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The share token behind a product's sales link.
 *
 * Products are no longer browsable — there is no catalogue. The operator creates one in the panel
 * and sends a link; that token is the whole address. It is unguessable rather than sequential for
 * the same reason an invoice pay-link is: the URL is the only thing standing between a stranger
 * and the page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('public_token', 40)->nullable()->unique()->after('slug');
        });

        // Backfill in chunks; a shop with a few thousand rows should not hold one transaction open.
        DB::table('products')->whereNull('public_token')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('products')->where('id', $row->id)->update(['public_token' => Str::random(40)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
