<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 9)->default('#a78bfa');
            $table->string('base_url')->default('https://api.openai.com/v1');
            $table->string('api_key');
            $table->string('model');
            $table->decimal('temperature', 4, 2)->nullable();
            $table->unsignedInteger('max_tokens')->nullable();
            $table->text('default_system_prompt')->nullable();
            $table->decimal('price_per_1m_input', 12, 6)->nullable();
            $table->decimal('price_per_1m_output', 12, 6)->nullable();
            $table->unsignedInteger('timeout_seconds')->default(120);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
