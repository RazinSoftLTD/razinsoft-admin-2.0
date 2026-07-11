<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('requester_type')->default('client')->after('client_id'); // client | employee
            $table->foreignId('group_id')->nullable()->after('category')->constrained('ticket_groups')->nullOnDelete();
            $table->foreignId('type_id')->nullable()->after('group_id')->constrained('ticket_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
            $table->dropConstrainedForeignId('type_id');
            $table->dropColumn('requester_type');
        });
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('ticket_groups');
    }
};
