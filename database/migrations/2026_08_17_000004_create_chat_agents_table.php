<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained();
            $table->unsignedInteger('position')->default(0);
            $table->text('initial_prompt')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->index(['chat_id', 'active', 'position']);
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->foreign('error_agent_id')->references('id')->on('chat_agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_agents');

        Schema::table('chats', function (Blueprint $table) {
            $table->dropForeign(['error_agent_id']);
        });
    }
};
