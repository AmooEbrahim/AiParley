<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Chat;
use App\Models\ChatAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_pages_render(): void
    {
        $agent = Agent::create([
            'name' => 'Tester',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'timeout_seconds' => 30,
        ]);

        $chat = Chat::create(['title' => 'Test chat']);
        $chat->chatAgents()->create([
            'agent_id' => $agent->id,
            'position' => 1,
            'active' => true,
            'joined_at' => now(),
        ]);

        $this->get('/')->assertOk();
        $this->get('/agents')->assertOk();
        $this->get("/agents/{$agent->id}/edit")->assertOk();
        $this->get('/chats/create')->assertOk();
        $this->get("/chats/{$chat->id}")->assertOk();
        $this->get('/settings')->assertOk();
    }

    public function test_poll_endpoint_returns_state_and_messages(): void
    {
        $agent = Agent::create([
            'name' => 'Tester',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'timeout_seconds' => 30,
        ]);

        $chat = Chat::create(['title' => 'Test chat']);
        $pivot = $chat->chatAgents()->create([
            'agent_id' => $agent->id,
            'position' => 1,
            'active' => true,
            'joined_at' => now(),
        ]);

        $chat->messages()->create([
            'chat_agent_id' => $pivot->id,
            'agent_id' => $agent->id,
            'content' => 'hello',
            'model' => 'gpt-4o-mini',
        ]);

        $response = $this->getJson("/api/chats/{$chat->id}/messages?after=0");

        $response->assertOk()
            ->assertJsonPath('status', 'idle')
            ->assertJsonPath('message_count', 1)
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.content', 'hello')
            ->assertJsonPath('stats.total_messages', 1);

        // Incremental poll returns nothing new
        $this->getJson("/api/chats/{$chat->id}/messages?after={$chat->messages()->first()->id}")
            ->assertOk()
            ->assertJsonCount(0, 'messages');
    }

    public function test_agent_can_be_created_and_soft_deleted(): void
    {
        $response = $this->post('/agents', [
            'name' => 'Socrates',
            'color' => '#a78bfa',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'timeout_seconds' => 60,
        ]);

        $response->assertRedirect(route('agents.index'));
        $this->assertDatabaseHas('agents', ['name' => 'Socrates']);

        $agent = Agent::first();
        ChatAgent::create([
            'chat_id' => Chat::create(['title' => 'x'])->id,
            'agent_id' => $agent->id,
            'position' => 1,
        ]);

        $this->delete("/agents/{$agent->id}");
        $this->assertSoftDeleted('agents', ['id' => $agent->id]);
    }

    public function test_chat_accepts_duplicate_agents_with_own_identity(): void
    {
        $agent = Agent::create([
            'name' => 'Twins',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'timeout_seconds' => 30,
        ]);

        $response = $this->post(route('chats.store'), [
            'title' => 'Same agent twice',
            'participants' => [
                ['agent_id' => $agent->id, 'display_name' => 'Good Twin', 'color' => '#22d3ee', 'position' => 1],
                ['agent_id' => $agent->id, 'display_name' => 'Evil Twin', 'color' => '#f87171', 'position' => 2],
            ],
        ]);

        $response->assertRedirect();
        $chat = Chat::where('title', 'Same agent twice')->first();

        $this->assertSame(2, $chat->chatAgents()->count(), 'Same agent must be attachable multiple times');
        $this->assertSame('#22d3ee', $chat->chatAgents()->orderBy('position')->get()[0]->color);
        $this->assertSame('#f87171', $chat->chatAgents()->orderBy('position')->get()[1]->color);
        $this->assertSame('Good Twin', $chat->chatAgents()->orderBy('position')->get()[0]->displayName());
        $this->assertSame('Evil Twin', $chat->chatAgents()->orderBy('position')->get()[1]->displayName());

        // Both pivots share one agent, pointing at the same underlying endpoint
        $this->assertSame($agent->id, $chat->chatAgents()->pluck('agent_id')->unique()->values()->sole());
    }

    public function test_messages_use_display_name_and_pivot_color(): void
    {
        $agent = Agent::create([
            'name' => 'Base',
            'color' => '#112233',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'timeout_seconds' => 30,
        ]);

        $chat = Chat::create(['title' => 'x']);
        $pivot = $chat->chatAgents()->create([
            'agent_id' => $agent->id,
            'color' => '#f472b6',
            'display_name' => 'Reza',
            'position' => 1,
        ]);

        $chat->messages()->create([
            'chat_agent_id' => $pivot->id,
            'agent_id' => $agent->id,
            'content' => 'hi',
        ]);

        $message = $chat->messages()->first();
        $display = $message->toDisplayArray();

        $this->assertSame('Reza', $display['agent_name']);
        $this->assertSame('#f472b6', $display['agent_color']);
    }
}
