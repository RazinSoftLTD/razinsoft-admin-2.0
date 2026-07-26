<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the "last import" banner be closed.
 *
 * Recorded on the batch rather than per-user: the banner is about one specific import, and an
 * import someone has already decided to keep is noise for every admin, not just the one who
 * closed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_import_batches', function (Blueprint $table) {
            $table->timestamp('dismissed_at')->nullable()->after('undone_at');
        });
    }

    public function down(): void
    {
        Schema::table('client_import_batches', function (Blueprint $table) {
            $table->dropColumn('dismissed_at');
        });
    }
};
