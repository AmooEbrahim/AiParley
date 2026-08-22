<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Services\AiClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    public function index(): View
    {
        return view('agents.index', [
            'agents' => Agent::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $agent = Agent::create($this->validated($request));

        return redirect()
            ->route('agents.index')
            ->with('success', "Agent \"{$agent->name}\" created.");
    }

    public function edit(Agent $agent): View
    {
        return view('agents.edit', ['agent' => $agent]);
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
        $request->validate([
            'base_url' => ['nullable', 'url'],
            'api_key' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
        ]);

        $probe = $agent->replicate()->fill(array_filter([
            'base_url' => $request->input('base_url'),
            'api_key' => $request->input('api_key'),
            'model' => $request->input('model'),
        ]));

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

    private function validated(Request $request, ?Agent $agent = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('agents', 'name')
                    ->when($agent, fn ($rule) => $rule->ignore($agent))
                    ->whereNull('deleted_at'),
            ],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'base_url' => ['required', 'url', 'max:255'],
            'api_key' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:120'],
            'temperature' => ['nullable', 'numeric', 'between:0,2'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'default_system_prompt' => ['nullable', 'string', 'max:20000'],
            'price_per_1m_input' => ['nullable', 'numeric', 'min:0'],
            'price_per_1m_output' => ['nullable', 'numeric', 'min:0'],
            'timeout_seconds' => ['required', 'integer', 'min:5', 'max:300'],
        ]);
    }
}
