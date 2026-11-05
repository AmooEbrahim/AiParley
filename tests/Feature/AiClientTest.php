<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Services\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_opencode_requests_carry_a_stable_session_header(): void
    {
        Http::fake([
            'https://opencode.ai/go/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'hi']]],
                'model' => 'opencode/grok-code',
            ]),
        ]);

        $agent = Agent::create([
            'name' => 'Go', 'color' => '#a78bfa',
            'base_url' => 'https://opencode.ai/go/v1', 'api_key' => 'sk-opencode',
            'model' => 'opencode/grok-code', 'timeout_seconds' => 30,
        ]);

        $result = app(AiClient::class)->complete(
            $agent,
            [['role' => 'user', 'content' => 'hello']],
            'parley-chat-42',
        );

        $this->assertSame('hi', $result['content']);

        Http::assertSent(fn ($request) => $request->url() === 'https://opencode.ai/go/v1/chat/completions'
            && $request->hasHeader('x-opencode-session', 'parley-chat-42'));
    }

    public function test_other_providers_do_not_receive_the_opencode_header(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'hi']]],
                'model' => 'gpt-4o-mini',
            ]),
        ]);

        $agent = Agent::create([
            'name' => 'OpenAI', 'color' => '#a78bfa',
            'base_url' => 'https://api.openai.com/v1', 'api_key' => 'sk-openai',
            'model' => 'gpt-4o-mini', 'timeout_seconds' => 30,
        ]);

        app(AiClient::class)->complete($agent, [['role' => 'user', 'content' => 'hello']], 'parley-chat-42');

        Http::assertSent(fn ($request) => ! $request->hasHeader('x-opencode-session'));
    }
}
