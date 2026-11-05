<?php

use App\Models\Agent;
use App\Models\Provider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('provider_id')->nullable()->after('color')->constrained('providers')->nullOnDelete();
            $table->string('base_url')->nullable()->change();
            $table->string('api_key')->nullable()->change();
        });

        // Attach existing agents to providers derived from their endpoint+key,
        // so they keep working through the relation instead of local copies.
        Agent::query()
            ->whereNull('provider_id')
            ->get(['id', 'base_url', 'api_key'])
            ->groupBy(fn (Agent $agent) => $agent->base_url.'|'.$agent->api_key)
            ->each(function ($group) {
                $first = $group->first();

                $provider = Provider::query()
                    ->where('base_url', $first->base_url)
                    ->where(fn ($q) => $q->where('api_key', $first->api_key)->orWhereNull('api_key'))
                    ->first() ?? $this->createProviderFor($first);

                if ((string) $provider->api_key === '' && (string) $first->api_key !== '') {
                    $provider->update(['api_key' => $first->api_key]);
                }

                Agent::query()
                    ->whereIn('id', $group->pluck('id'))
                    ->when((string) $provider->api_key !== '', fn ($q) => $q->update([
                        'provider_id' => $provider->id,
                        'base_url' => null,
                        'api_key' => null,
                    ]), fn ($q) => $q->update(['provider_id' => $provider->id, 'base_url' => null]));
            });
    }

    private function createProviderFor(Agent $agent): Provider
    {
        $host = parse_url((string) $agent->base_url, PHP_URL_HOST) ?: 'Custom';

        $name = trim(str($host)->replaceFirst('api.', '')->headline());

        if (mb_strlen($name) > 120) {
            $name = mb_substr($name, 0, 120);
        }

        $base = $name;
        $i = 2;

        while (Provider::query()->where('name', $name)->exists()) {
            $name = $base.' '.$i++;
        }

        return Provider::query()->create([
            'name' => $name,
            'base_url' => (string) $agent->base_url,
            'api_key' => $agent->api_key,
        ]);
    }

    public function down(): void
    {
        // Move provider config back onto agents so nothing loses its endpoint.
        foreach (Agent::withTrashed()->get() as $agent) {
            if ($agent->provider_id !== null && ($agent->base_url === null || $agent->api_key === null)) {
                $agent->forceFill([
                    'base_url' => $agent->base_url ?? $agent->provider?->base_url,
                    'api_key' => $agent->api_key ?? $agent->provider?->api_key,
                ])->save();
            }
        }

        Schema::table('agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('provider_id');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->string('base_url')->default('https://api.openai.com/v1')->change();
            $table->string('api_key')->nullable(false)->change();
        });

        Schema::dropIfExists('providers');
    }
};
