<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the CodeCanyon market-research tables.
 *
 * That module was RazinSoft's own tooling for watching Envato listings. It has no business in a
 * panel somebody else runs, so the code is gone; these tables would otherwise sit in every install
 * forever, holding nothing.
 *
 * Deliberately one-way. Rebuilding empty tables for a module that no longer exists would leave an
 * install in a state no code can read, which is worse than not rolling back at all.
 */
return new class extends Migration
{
    /** Children first: products point at niches, snapshots point at products. */
    private const TABLES = ['envato_snapshots', 'envato_products', 'envato_authors', 'envato_niches', 'envato_settings'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Nothing to restore.
    }
};
