<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Provider;
use App\Services\AiClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    public function index(Request $request): View
    {
        return view('agents.index', [
            'agents' => Agent::query()->with('provider')->orderBy('name')->get(),
            'providers' => $this->savedProviders(),
            'presets' => Provider::PRESETS,
            'editId' => (int) $request->query('edit'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $agent = Agent::create($this->validated($request));

        return redirect()
            ->route('agents.index')
            ->with('success', "Agent \"{$agent->name}\" created.");
    }

    public function update(Request $request, Agent $agent): RedirectResponse
    {
        $agent->update($this->validated($request, $agent));

        return redirect()
            ->route('agents.index')
            ->with('success', "Agent \"{$agent->name}\" updated.");
    }

    public function destroy(Agent $agent): RedirectResponse
    {
        $agent->delete();

        return redirect()
            ->route('agents.index')
            ->with('success', "Agent \"{$agent->name}\" deleted. It stays visible in existing chats.");
    }

    public function test(Request $request, Agent $agent, AiClient $client)
    {
        $data = $request->validate([
            'base_url' => ['nullable', 'url'],
            'api_key' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
            'provider_id' => ['nullable', Rule::exists('providers', 'id')],
        ]);

        // Probe with exactly what the form shows: an explicit provider, or
        // explicit overrides over the agent's current resolved config.
        $probe = new Agent;
        $probe->forceFill([
            'name' => $agent->name,
            'model' => $data['model'] ?? $agent->model,
            'timeout_seconds' => $agent->timeout_seconds,
        ]);

        if (! empty($data['provider_id'])) {
            /** @var Provider $provider */
            $provider = Provider::query()->findOrFail($data['provider_id']);
            $probe->forceFill([
                'base_url' => $data['base_url'] ?? $provider->base_url,
                'api_key' => array_key_exists('api_key', $data) && $data['api_key'] !== null
                    ? $data['api_key']
                    : $provider->api_key,
            ]);
        } else {
            $probe->forceFill([
                'base_url' => $data['base_url'] ?? $agent->resolveBaseUrl(),
                'api_key' => array_key_exists('api_key', $data) && $data['api_key'] !== null
                    ? $data['api_key']
                    : $agent->resolveApiKey(),
            ]);
        }

        try {
            $result = $client->ping($probe);

            return response()->json([
                'ok' => true,
                'latency_ms' => $result['latency_ms'],
                'model' => $result['model'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 200);
        }
    }

    private function savedProviders(): array
    {
        return Provider::query()
            ->withCount('agents')
            ->orderBy('name')
            ->get()
            ->reject(fn (Provider $p) => $p->isPresetPlaceholder())
            ->values()
            ->map(fn (Provider $p) => [
                'id' => (string) $p->id,
                'name' => $p->name,
                'base_url' => $p->base_url,
                'has_key' => (string) $p->api_key !== '',
                'agents_count' => $p->agents_count,
                'models_count' => count($p->cachedModels()),
                'is_active' => $p->isActive(),
                'models_cached_at' => $p->models_cached_at?->toIso8601String(),
            ])
            ->all();
    }

    private function validated(Request $request, ?Agent $agent = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('agents', 'name')
                    ->when($agent, fn ($rule) => $rule->ignore($agent))
                    ->whereNull('deleted_at'),
            ],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'provider_id' => ['nullable', Rule::exists('providers', 'id')],
            'base_url' => ['nullable', 'url', 'max:255', 'required_without:provider_id'],
            'api_key' => ['nullable', 'string', 'max:255', 'required_without:provider_id'],
            'model' => ['required', 'string', 'max:120'],
            'temperature' => ['nullable', 'numeric', 'between:0,2'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'default_system_prompt' => ['nullable', 'string', 'max:20000'],
            'price_per_1m_input' => ['nullable', 'numeric', 'min:0'],
            'price_per_1m_output' => ['nullable', 'numeric', 'min:0'],
            'timeout_seconds' => ['required', 'integer', 'min:5', 'max:300'],
        ]);

        if (array_key_exists('provider_id', $data) && $data['provider_id'] !== null) {
            // Endpoint + credentials come from the provider.
            $data['base_url'] = null;
            $data['api_key'] = null;
        } else {
            unset($data['provider_id']);
        }

        return $data;
    }
}
