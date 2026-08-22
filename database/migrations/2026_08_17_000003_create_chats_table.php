<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status', 20)->default('idle'); // idle | running | error
            $table->unsignedInteger('auto_budget')->default(20);
            $table->unsignedInteger('message_limit')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('error_agent_id')->nullable(); // FK -> chat_agents (added later)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
