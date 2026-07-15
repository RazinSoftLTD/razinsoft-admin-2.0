<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Track each client import as a batch so the admin can undo the last import. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_key')->unique();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('count')->default(0);
            $table->timestamp('undone_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // Tag imported clients with their batch so an undo can find them precisely.
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'import_batch')) {
                $table->string('import_batch')->nullable()->index()->after('created_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'import_batch')) {
                $table->dropColumn('import_batch');
            }
        });
        Schema::dropIfExists('client_import_batches');
    }
};
