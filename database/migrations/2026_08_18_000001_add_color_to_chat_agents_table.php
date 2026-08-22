<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_agents', function (Blueprint $table) {
            $table->string('color', 9)->nullable()->after('agent_id');
        });

        // Backfill existing pivots with their agent's color.
        DB::table('chat_agents')
            ->whereNull('color')
            ->update(['color' => DB::raw('(SELECT color FROM agents WHERE agents.id = chat_agents.agent_id)')]);
    }

    public function down(): void
    {
        Schema::table('chat_agents', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
