<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Token for the client-facing PRD link. Null = link not shared / revoked.
            $table->string('prd_share_token', 64)->nullable()->unique()->after('prd_sections');
        });

        Schema::table('project_prd_items', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('note');   // pending · approved · rejected
            $table->string('submitted_by_name')->nullable()->after('uploaded_by'); // set when a client submits via the link
            $table->foreignId('approved_by')->nullable()->after('submitted_by_name')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('review_note')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('project_prd_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'submitted_by_name', 'approved_at', 'review_note']);
        });
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn('prd_share_token'));
    }
};
