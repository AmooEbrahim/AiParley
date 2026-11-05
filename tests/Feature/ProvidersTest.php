<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProvidersTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_can_be_created_updated_and_deleted(): void
    {
        $this->post('/providers', [
            'name' => 'My Router',
            'base_url' => 'https://openrouter.ai/api/v1',
            'api_key' => 'sk-or-test',
        ])->assertRedirect(route('agents.index'));

        $provider = Provider::where('name', 'My Router')->sole();
        $this->assertSame('sk-or-test', $provider->api_key);

        // Blank key on update keeps the stored one.
        $this->put("/providers/{$provider->id}", [
            'name' => 'My Router 2',
            'base_url' => 'https://openrouter.ai/api/v1',
        ])->assertRedirect(route('agents.index'));

        $provider->refresh();
        $this->assertSame('My Router 2', $provider->name);
        $this->assertSame('sk-or-test', $provider->api_key);

        $agent = Agent::create([
            'name' => 'Dependent', 'color' => '#112233', 'provider_id' => $provider->id,
            'model' => 'm1', 'timeout_seconds' => 30,
        ]);

        $this->delete("/providers/{$provider->id}")->assertRedirect(route('agents.index'));

        $this->assertModelMissing($provider);
        $this->assertNull($agent->fresh()->provider_id);
        // Agent keeps its own copy of the endpoint settings.
        $this->assertSame('https://openrouter.ai/api/v1', $agent->fresh()->base_url);
        $this->assertSame('sk-or-test', $agent->fresh()->api_key);
    }

    public function test_agent_can_use_a_provider_without_own_endpoint(): void
    {
        $provider = Provider::create([
            'name' => 'Router', 'base_url' => 'https://openrouter.ai/api/v1', 'api_key' => 'sk-shared',
        ]);

        $this->post('/agents', [
            'name' => 'ViaProvider',
            'color' => '#a78bfa',
            'provider_id' => $provider->id,
            'model' => 'openai/gpt-4o-mini',
            'timeout_seconds' => 60,
        ])->assertRedirect(route('agents.index'));

        $agent = Agent::where('name', 'ViaProvider')->sole();

        $this->assertSame($provider->id, $agent->provider_id);
        $this->assertNull($agent->base_url);
        $this->assertNull($agent->api_key);
        $this->assertSame('https://openrouter.ai/api/v1', $agent->resolveBaseUrl());
        $this->assertSame('sk-shared', $agent->resolveApiKey());
    }

    public function test_custom_agent_still_requires_endpoint_and_key(): void
    {
        $this->post('/agents', [
            'name' => 'Broken',
            'color' => '#a78bfa',
            'model' => 'gpt-4o-mini',
            'timeout_seconds' => 60,
        ])->assertSessionHasErrors(['base_url', 'api_key']);
    }

    public function test_switching_agent_to_provider_clears_local_overrides(): void
    {
        $provider = Provider::create([
            'name' => 'Router', 'base_url' => 'https://openrouter.ai/api/v1', 'api_key' => 'sk-shared',
        ]);

        $agent = Agent::create([
            'name' => 'Switcher', 'color' => '#a78bfa', 'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-own', 'model' => 'gpt-4o-mini', 'timeout_seconds' => 30,
        ]);

        $this->put("/agents/{$agent->id}", [
            'name' => 'Switcher',
            'color' => '#a78bfa',
            'provider_id' => $provider->id,
            'model' => 'openai/gpt-4o-mini',
            'timeout_seconds' => 30,
        ])->assertRedirect(route('agents.index'));

        $agent->refresh();
        $this->assertSame($provider->id, $agent->provider_id);
        $this->assertNull($agent->base_url);
        $this->assertNull($agent->api_key);
    }

    public function test_models_endpoint_fetches_normalizes_and_caches(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/models' => Http::response([
                'data' => [
                    [
                        'id' => 'openai/gpt-4o-mini',
                        'name' => 'OpenAI: GPT-4o-mini',
                        'context_length' => 128000,
                        'pricing' => ['prompt' => '0.00000015', 'completion' => '0.0000006'],
                    ],
                    ['id' => 'free/model', 'pricing' => ['prompt' => '0', 'completion' => '0']],
                ],
            ]),
        ]);

        $provider = Provider::create([
            'name' => 'Router', 'base_url' => 'https://openrouter.ai/api/v1', 'api_key' => 'sk-x',
        ]);

        $response = $this->getJson("/api/providers/{$provider->id}/models");

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('count', 2)
            ->assertJsonPath('models.0.id', 'free/model')
            ->assertJsonPath('models.1.id', 'openai/gpt-4o-mini')
            ->assertJsonPath('models.1.context_length', 128000)
            ->assertJsonPath('models.1.price_in_1m', 0.15)
            ->assertJsonPath('models.1.price_out_1m', 0.6);

        $provider->refresh();
        $this->assertCount(2, $provider->cachedModels());
        $this->assertNotNull($provider->models_cached_at);
    }

    public function test_models_endpoint_reports_errors_but_keeps_cache(): void
    {
        Http::fake([
            'https://bad.example/v1/models' => Http::response(['error' => ['message' => 'nope']], 403),
        ]);

        $provider = Provider::create(['name' => 'Bad', 'base_url' => 'https://bad.example/v1']);

        $this->getJson("/api/providers/{$provider->id}/models")
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'HTTP 403: nope');
    }

    public function test_providers_are_managed_inline_on_agents_page(): void
    {
        $configured = Provider::create([
            'name' => 'Router', 'base_url' => 'https://openrouter.ai/api/v1', 'api_key' => 'sk-x',
        ]);
        $preset = Provider::where('name', 'OpenAI')->sole();

        $response = $this->get('/agents');

        $response->assertOk()
            ->assertSee('Router')
            ->assertSee('Add custom provider');

        // Configured providers render in the active section...
        $this->assertTrue($configured->fresh()->isActive());
        // ...untouched presets stay available-to-add and are not "active".
        $this->assertFalse($preset->fresh()->isActive());
    }

    public function test_agent_edit_pages_are_gone_but_updates_work(): void
    {
        $agent = Agent::create([
            'name' => 'Inline', 'color' => '#a78bfa', 'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-x', 'model' => 'gpt-4o-mini', 'timeout_seconds' => 30,
        ]);

        $this->get("/agents/{$agent->id}/edit")->assertNotFound();

        $this->put("/agents/{$agent->id}", [
            'name' => 'Inline 2', 'color' => '#a78bfa', 'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-x', 'model' => 'gpt-4o-mini', 'timeout_seconds' => 30,
        ])->assertRedirect(route('agents.index'));

        $this->assertSame('Inline 2', $agent->fresh()->name);
    }
}
