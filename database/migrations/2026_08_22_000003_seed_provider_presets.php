<?php

use App\Models\Provider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Seed the known provider catalog. Idempotent: names are unique. */
    public function up(): void
    {
        foreach (Provider::PRESETS as $preset) {
            DB::table('providers')->insertOrIgnore($preset + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Only remove untouched presets; keep anything the user configured.
        DB::table('providers')
            ->whereIn('name', array_column(Provider::PRESETS, 'name'))
            ->whereNull('api_key')
            ->whereNull('models_cache')
            ->whereNotIn('id', DB::table('agents')->select('provider_id')->whereNotNull('provider_id'))
            ->delete();
    }
};
