<?php

namespace Tests\Feature;

use App\Jobs\ProcessTurnJob;
use App\Models\Agent;
use App\Models\Chat;
use App\Models\Setting;
use App\Services\AiClient;
use App\Services\ContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TurnEngineTest extends TestCase
{
    use RefreshDatabase;

    private Chat $chat;

    private array $pivots;

    protected function setUp(): void
    {
        parent::setUp();

        $alpha = Agent::create([
            'name' => 'Alpha',
            'color' => '#22d3ee',
            'base_url' => 'https://api.alpha.test/v1',
            'api_key' => 'sk-alpha',
            'model' => 'alpha-model',
            'default_system_prompt' => 'You are Alpha.',
            'price_per_1m_input' => 0.5,
            'price_per_1m_output' => 1.5,
            'timeout_seconds' => 30,
        ]);

        $beta = Agent::create([
            'name' => 'Beta',
            'color' => '#f472b6',
            'base_url' => 'https://api.beta.test/v1',
            'api_key' => 'sk-beta',
            'model' => 'beta-model',
            'timeout_seconds' => 30,
        ]);

        $this->chat = Chat::create(['title' => 'Test chat', 'auto_budget' => 2]);

        $this->pivots = [
            'alpha' => $this->chat->chatAgents()->create([
                'agent_id' => $alpha->id,
                'position' => 1,
                'active' => true,
                'joined_at' => now(),
            ]),
            'beta' => $this->chat->chatAgents()->create([
                'agent_id' => $beta->id,
                'position' => 2,
                'initial_prompt' => 'Be terse, Beta.',
                'active' => true,
                'joined_at' => now(),
            ]),
        ];
    }

    public function test_context_builds_roles_per_perspective(): void
    {
        Setting::put('global_prompt', 'Global rules.');

        $this->chat->messages()->create([
            'chat_agent_id' => $this->pivots['alpha']->id,
            'agent_id' => $this->pivots['alpha']->agent_id,
            'content' => 'Hello from Alpha.',
        ]);

        $builder = app(ContextBuilder::class);
        $this->pivots['alpha']->refresh();
        $this->pivots['beta']->refresh();

        // From Alpha's perspective: own message is assistant, system = global + default.
        // A user-role nudge is appended because the history ends with Alpha's own message.
        $alphaView = $builder->build($this->pivots['alpha']);
        $this->assertSame('system', $alphaView[0]['role']);
        $this->assertSame("Global rules.\n\nYou are Alpha.", $alphaView[0]['content']);
        $this->assertSame('assistant', $alphaView[1]['role']);
        $this->assertSame('Hello from Alpha.', $alphaView[1]['content']);
        $this->assertSame('user', $alphaView[2]['role']);

        // From Beta's perspective: Alpha's message is user with speaker prefix
        $betaView = $builder->build($this->pivots['beta']);
        $this->assertSame("Global rules.\n\nBe terse, Beta.", $betaView[0]['content']);
        $this->assertSame('user', $betaView[1]['role']);
        $this->assertSame('[Alpha]: Hello from Alpha.', $betaView[1]['content']);
    }

    public function test_empty_prompt_parts_are_skipped(): void
    {
        $builder = app(ContextBuilder::class);
        $this->pivots['beta']->refresh();

        // No global prompt, Beta has no default, only chat prompt
        $this->assertSame('Be terse, Beta.', $builder->systemPrompt($this->pivots['beta']));

        // Alpha has default only (no global, no chat prompt)
        $this->pivots['alpha']->refresh();
        $this->assertSame('You are Alpha.', $builder->systemPrompt($this->pivots['alpha']));
    }

    public function test_manual_turn_saves_message_with_metadata(): void
    {
        Http::fake([
            'api.alpha.test/*' => Http::response([
                'choices' => [['message' => ['content' => 'Greetings!']]],
                'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 200],
                'model' => 'alpha-model',
            ]),
        ]);

        (new ProcessTurnJob($this->chat->id, manual: true))->handle(app(AiClient::class), app(ContextBuilder::class));

        $message = $this->chat->messages()->first();
        $this->assertNotNull($message);
        $this->assertSame('Greetings!', $message->content);
        $this->assertSame('alpha-model', $message->model);
        $this->assertSame(1000, $message->prompt_tokens);
        $this->assertSame(200, $message->completion_tokens);
        // (1000/1M)*0.5 + (200/1M)*1.5 = 0.0005 + 0.0003 = 0.0008
        $this->assertEqualsWithDelta(0.0008, $message->cost, 0.000001);
        $this->assertNotNull($message->latency_ms);
        $this->assertSame('idle', $this->chat->refresh()->status, 'Manual turn must leave chat idle');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.alpha.test/v1/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer sk-alpha');
        });
    }

    public function test_auto_run_round_robin_and_budget_stop(): void
    {
        Http::fake([
            'api.alpha.test/*' => Http::response([
                'choices' => [['message' => ['content' => 'From Alpha']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
                'model' => 'alpha-model',
            ]),
            'api.beta.test/*' => Http::response([
                'choices' => [['message' => ['content' => 'From Beta']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
                'model' => 'beta-model',
            ]),
        ]);

        // Emulate pressing Start: budget 2 → limit = 0 + 2
        $this->chat->update([
            'status' => 'running',
            'message_limit' => $this->chat->messageCount() + $this->chat->auto_budget,
        ]);

        (new ProcessTurnJob($this->chat->id))->handle(app(AiClient::class), app(ContextBuilder::class));

        $this->chat->refresh();
        $this->assertSame('idle', $this->chat->status, 'Auto-run must stop at budget');
        $this->assertSame(2, $this->chat->messageCount());

        // Round-robin: Alpha (pos 1) then Beta (pos 2)
        $speakers = $this->chat->messages()->orderBy('id')->pluck('chat_agent_id');
        $this->assertSame([$this->pivots['alpha']->id, $this->pivots['beta']->id], $speakers->all());
    }

    public function test_final_failure_sets_error_state(): void
    {
        Http::fake([
            'api.alpha.test/*' => Http::response(['error' => ['message' => 'Invalid API key']], 401),
        ]);

        $job = new ProcessTurnJob($this->chat->id, manual: true);

        try {
            $job->handle(app(AiClient::class), app(ContextBuilder::class));
            $this->fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            $job->failed($e);
        }

        $this->chat->refresh();
        $this->assertSame('error', $this->chat->status);
        $this->assertStringContainsString('Invalid API key', $this->chat->last_error);
        $this->assertSame($this->pivots['alpha']->id, $this->chat->error_agent_id);
    }

    public function test_swapped_agent_sees_history_as_user(): void
    {
        $this->chat->messages()->create([
            'chat_agent_id' => $this->pivots['alpha']->id,
            'agent_id' => $this->pivots['alpha']->agent_id,
            'content' => 'Old Alpha message.',
        ]);

        // Swap: deactivate old pivot, create a new one for a fresh agent
        $this->pivots['alpha']->update(['active' => false, 'left_at' => now()]);
        $gamma = Agent::create([
            'name' => 'Gamma',
            'base_url' => 'https://api.gamma.test/v1',
            'api_key' => 'sk-gamma',
            'model' => 'gamma-model',
            'timeout_seconds' => 30,
        ]);
        $gammaPivot = $this->chat->chatAgents()->create([
            'agent_id' => $gamma->id,
            'position' => 1,
            'active' => true,
            'joined_at' => now(),
        ]);

        $view = app(ContextBuilder::class)->build($gammaPivot);

        $this->assertSame('user', $view[0]['role']);
        $this->assertSame('[Alpha]: Old Alpha message.', $view[0]['content']);
    }

    public function test_display_name_used_in_context_prefixes(): void
    {
        $this->pivots['alpha']->update(['display_name' => 'Socrates']);

        $this->chat->messages()->create([
            'chat_agent_id' => $this->pivots['alpha']->id,
            'agent_id' => $this->pivots['alpha']->agent_id,
            'content' => 'I know nothing.',
        ]);

        $view = app(ContextBuilder::class)->build($this->pivots['beta']);

        // view[0] is Beta's system prompt — the history follows.
        $this->assertSame('user', $view[1]['role']);
        $this->assertSame('[Socrates]: I know nothing.', $view[1]['content']);
    }

    public function test_fresh_chat_context_gets_kickoff_user_message(): void
    {
        // Regression: providers return an empty response when the request has
        // only a system message — the builder must always end with a user turn.
        $view = app(ContextBuilder::class)->build($this->pivots['alpha']);

        $this->assertCount(2, $view);
        $this->assertSame('system', $view[0]['role']);
        $this->assertSame('You are Alpha.', $view[0]['content']);
        $this->assertSame('user', $view[1]['role']);
        $this->assertNotSame('', trim($view[1]['content']));
    }

    public function test_client_falls_back_to_reasoning_content(): void
    {
        Http::fake([
            'api.alpha.test/*' => Http::response([
                'choices' => [['message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'reasoning_content' => 'The reasoning text.',
                ], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
                'model' => 'alpha-model',
            ]),
        ]);

        $result = app(AiClient::class)->complete($this->pivots['alpha']->agent, [
            ['role' => 'user', 'content' => 'hi'],
        ]);

        $this->assertSame('The reasoning text.', $result['content']);
    }

    public function test_client_joins_array_content_parts(): void
    {
        Http::fake([
            'api.alpha.test/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => [
                    ['type' => 'text', 'text' => 'Part one.'],
                    ['type' => 'text', 'text' => 'Part two.'],
                ]]]],
                'model' => 'alpha-model',
            ]),
        ]);

        $result = app(AiClient::class)->complete($this->pivots['alpha']->agent, [
            ['role' => 'user', 'content' => 'hi'],
        ]);

        $this->assertSame("Part one.\nPart two.", $result['content']);
    }

    public function test_client_error_includes_response_body(): void
    {
        Http::fake([
            'api.alpha.test/*' => Http::response('', 200),
        ]);

        try {
            app(AiClient::class)->complete($this->pivots['alpha']->agent, [
                ['role' => 'user', 'content' => 'hi'],
            ]);
            $this->fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('(empty)', $e->getMessage());
            $this->assertStringContainsString('finish_reason', $e->getMessage());
        }
    }
}
