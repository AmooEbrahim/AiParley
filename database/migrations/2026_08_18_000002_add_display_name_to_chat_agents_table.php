<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_agents', function (Blueprint $table) {
            $table->string('display_name', 120)->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('chat_agents', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
