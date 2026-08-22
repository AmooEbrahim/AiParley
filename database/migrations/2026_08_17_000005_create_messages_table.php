<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained();
            $table->text('content');
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->decimal('cost', 12, 6)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('model')->nullable();
            $table->timestamps();

            $table->index(['chat_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
