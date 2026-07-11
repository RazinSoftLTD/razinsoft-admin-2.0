<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('enabled'); // enabled | disabled
            $table->timestamps();
        });
        Schema::create('ticket_agent_group', function (Blueprint $table) {
            $table->foreignId('ticket_agent_id')->constrained('ticket_agents')->cascadeOnDelete();
            $table->foreignId('ticket_group_id')->constrained('ticket_groups')->cascadeOnDelete();
            $table->primary(['ticket_agent_id', 'ticket_group_id']);
        });
        Schema::create('reply_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_agent_group');
        Schema::dropIfExists('ticket_agents');
        Schema::dropIfExists('reply_templates');
    }
};
