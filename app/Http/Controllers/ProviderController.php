<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Services\AiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProviderController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Preset rows are catalog placeholders, not user data: reuse the empty
        // one instead of creating a twin. Configured names get a suffix so a
        // template can be instantiated as many times as the user likes.
        $placeholder = $this->placeholderNamed((string) $request->input('name'));

        if ($placeholder !== null) {
            $placeholder->update($this->validated($request, $placeholder));
            $provider = $placeholder;
        } else {
            $request->merge(['name' => $this->uniqueName((string) $request->input('name'))]);
            $provider = Provider::create($this->validated($request));
        }

        return redirect()
            ->route('agents.index')
            ->with('success', "Provider \"{$provider->name}\" created.");
    }

    public function update(Request $request, Provider $provider): RedirectResponse
    {
        $provider->update($this->validated($request, $provider));

        return redirect()
            ->route('agents.index')
            ->with('success', "Provider \"{$provider->name}\" updated.");
    }

    public function destroy(Provider $provider): RedirectResponse
    {
        $name = $provider->name;
        $agentCount = $provider->agents()->count();

        // Hand each agent its own copy so they keep working after the detach.
        DB::transaction(function () use ($provider) {
            $provider->agents()->update([
                'base_url' => $provider->base_url,
                'api_key' => $provider->api_key,
            ]);

            $provider->delete();
        });

        $suffix = $agentCount > 0 ? " Its {$agentCount} agent(s) keep their own endpoint settings." : '';

        return redirect()
            ->route('agents.index')
            ->with('success', "Provider \"{$name}\" deleted.{$suffix}");
    }

    /**
     * Force a refresh of the provider's model catalog.
     */
    public function refreshModels(Request $request, Provider $provider, AiClient $client)
    {
        try {
            $models = $this->fetchAndCache($provider, $client);

            if ($request->expectsJson()) {
                return response()->json(['ok' => true, 'count' => count($models)]);
            }

            return redirect()
                ->route('agents.index')
                ->with('success', 'Fetched '.count($models).' models from "'.$provider->name.'".');
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 200);
            }

            return redirect()
                ->route('agents.index')
                ->with('error', 'Could not fetch models: '.$e->getMessage());
        }
    }

    /**
     * JSON model list for the agent form combobox. Serves the cached catalog,
     * refetching when empty, stale (> 6h) or when ?refresh=1 is passed.
     */
    public function models(Request $request, Provider $provider, AiClient $client): JsonResponse
    {
        $refresh = $request->boolean('refresh');

        $fresh = ! $refresh
            && $provider->models_cached_at !== null
            && $provider->models_cached_at->gt(now()->subHours(6))
            && $provider->cachedModels() !== [];

        try {
            $models = $fresh ? $provider->cachedModels() : $this->fetchAndCache($provider, $client);
        } catch (\Throwable $e) {
            $models = $provider->cachedModels();

            if ($models === []) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 200);
            }
        }

        return response()->json([
            'ok' => true,
            'count' => count($models),
            'fetched_at' => $provider->models_cached_at?->toIso8601String(),
            'models' => $models,
        ]);
    }

    /** @return array<int, array{id: string, name: string, context_length: ?int, price_in_1m: ?float, price_out_1m: ?float}> */
    private function fetchAndCache(Provider $provider, AiClient $client): array
    {
        $models = $client->listModels($provider);

        $provider->forceFill([
            'models_cache' => $models,
            'models_cached_at' => now(),
        ])->save();

        return $models;
    }

    /** An untouched catalog row with this exact name, if one exists. */
    private function placeholderNamed(string $name): ?Provider
    {
        if (trim($name) === '') {
            return null;
        }

        $provider = Provider::query()->where('name', $name)->first();

        return $provider?->isPresetPlaceholder() ? $provider : null;
    }

    /** Keep names unique when the same template is added more than once. */
    private function uniqueName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || ! Provider::query()->where('name', $name)->exists()) {
            return $name;
        }

        $base = $name;
        $i = 2;

        while (Provider::query()->where('name', $name)->exists()) {
            $name = $base.' '.$i++;
        }

        return $name;
    }

    private function validated(Request $request, ?Provider $provider = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('providers', 'name')
                    ->when($provider, fn ($rule) => $rule->ignore($provider)),
            ],
            'base_url' => ['required', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
        ]);

        // On update, a blank key means "keep the stored one".
        if ($provider !== null && ($data['api_key'] ?? '') === '') {
            unset($data['api_key']);
        }

        return $data;
    }
}
