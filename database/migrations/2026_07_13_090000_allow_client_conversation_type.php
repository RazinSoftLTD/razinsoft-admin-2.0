<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Widen conversations.type to also allow 'client' (customer ↔ team messaging). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA writable_schema = 1');
            DB::statement("UPDATE sqlite_master SET sql = REPLACE(sql, \"in ('direct', 'group')\", \"in ('direct', 'group', 'client')\") WHERE type = 'table' AND name = 'conversations'");
            DB::statement('PRAGMA writable_schema = 0');
        } else {
            DB::statement("ALTER TABLE conversations MODIFY type VARCHAR(20) NOT NULL DEFAULT 'direct'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA writable_schema = 1');
            DB::statement("UPDATE sqlite_master SET sql = REPLACE(sql, \"in ('direct', 'group', 'client')\", \"in ('direct', 'group')\") WHERE type = 'table' AND name = 'conversations'");
            DB::statement('PRAGMA writable_schema = 0');
        }
    }
};
