<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_agent_type', function (Blueprint $table) {
            $table->foreignId('ticket_agent_id')->constrained('ticket_agents')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->cascadeOnDelete();
            $table->primary(['ticket_agent_id', 'ticket_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_agent_type');
    }
};
