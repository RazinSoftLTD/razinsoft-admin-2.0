<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment/delivery milestones agreed while a deal is still being negotiated. Deliberately
 * its own table — project_milestones belongs to delivery and carries a different shape
 * (status, colour, summary). A project can import these once, then the two diverge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['deal_id', 'due_date']);
        });

        // Which deal a project was created from — powers the optional "import from deal".
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('deal_id')->nullable()->after('client_id')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deal_id');
        });
        Schema::dropIfExists('deal_milestones');
    }
};
