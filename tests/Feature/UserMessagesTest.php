<?php

namespace Tests\Feature;

use App\Jobs\ProcessTurnJob;
use App\Models\Agent;
use App\Models\Chat;
use App\Services\AiClient;
use App\Services\ContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserMessagesTest extends TestCase
{
    use RefreshDatabase;

    private Chat $chat;

    private array $pivots;

    protected function setUp(): void
    {
        parent::setUp();

        $alpha = Agent::create([
            'name' => 'Alpha', 'color' => '#22d3ee',
            'base_url' => 'https://api.alpha.test/v1', 'api_key' => 'sk-a',
            'model' => 'alpha-model', 'timeout_seconds' => 30,
        ]);
        $beta = Agent::create([
            'name' => 'Beta', 'color' => '#f472b6',
            'base_url' => 'https://api.beta.test/v1', 'api_key' => 'sk-b',
            'model' => 'beta-model', 'timeout_seconds' => 30,
        ]);
        $gamma = Agent::create([
            'name' => 'Gamma', 'color' => '#a3e635',
            'base_url' => 'https://api.gamma.test/v1', 'api_key' => 'sk-g',
            'model' => 'gamma-model', 'timeout_seconds' => 30,
        ]);

        $this->chat = Chat::create(['title' => 'Test chat']);

        $this->pivots = [
            'alpha' => $this->chat->chatAgents()->create(['agent_id' => $alpha->id, 'position' => 1, 'active' => true, 'joined_at' => now()]),
            'beta' => $this->chat->chatAgents()->create(['agent_id' => $beta->id, 'position' => 2, 'active' => true, 'joined_at' => now()]),
            'gamma' => $this->chat->chatAgents()->create(['agent_id' => $gamma->id, 'position' => 3, 'active' => true, 'joined_at' => now()]),
        ];
    }

    public function test_impersonated_message_is_stored_like_agent_message(): void
    {
        $res = $this->postJson(route('chat-messages.store', $this->chat), [
            'kind' => 'agent',
            'chat_agent' => $this->pivots['alpha']->id,
            'content' => 'Alpha here, sorry I am late.',
        ]);

        $res->assertOk()->assertJsonPath('ok', true);

        $message = $this->chat->messages()->first();
        $this->assertSame('impersonated', $message->type);
        $this->assertSame($this->pivots['alpha']->id, $message->chat_agent_id);
        $this->assertSame($this->pivots['alpha']->agent_id, $message->agent_id);

        // Own perspective: assistant role
        $view = app(ContextBuilder::class)->build($this->pivots['alpha']);
        $this->assertSame('assistant', $view[0]['role']);
        $this->assertSame('Alpha here, sorry I am late.', $view[0]['content']);

        // Other perspective: user role with speaker prefix
        $view = app(ContextBuilder::class)->build($this->pivots['beta']);
        $this->assertSame('user', $view[0]['role']);
        $this->assertSame('[Alpha]: Alpha here, sorry I am late.', $view[0]['content']);
    }

    public function test_targeted_note_is_only_seen_by_its_target(): void
    {
        $this->postJson(route('chat-messages.store', $this->chat), [
            'kind' => 'note',
            'target' => $this->pivots['beta']->id,
            'content' => 'Beta, mention the weather.',
        ])->assertOk();

        $alphaView = app(ContextBuilder::class)->build($this->pivots['alpha']);
        $gammaView = app(ContextBuilder::class)->build($this->pivots['gamma']);

        // Others must not see the note (their context holds only the kickoff nudge, no note content)
        $this->assertNotContains('Beta, mention the weather.', array_column($alphaView, 'content'));
        $this->assertNotContains('Beta, mention the weather.', array_column($gammaView, 'content'));

        $betaView = app(ContextBuilder::class)->build($this->pivots['beta']);
        $this->assertSame('user', end($betaView)['role']);
        $this->assertSame('[Director]: Beta, mention the weather.', end($betaView)['content']);
    }

    public function test_untargeted_note_is_seen_by_everyone(): void
    {
        $this->postJson(route('chat-messages.store', $this->chat), [
            'kind' => 'note',
            'target' => null,
            'content' => 'Everyone, keep it short.',
        ])->assertOk();

        foreach (['alpha', 'beta', 'gamma'] as $key) {
            $view = app(ContextBuilder::class)->build($this->pivots[$key]);
            $this->assertSame('user', $view[0]['role']);
            $this->assertSame('[Director]: Everyone, keep it short.', $view[0]['content']);
        }
    }

    public function test_messages_can_be_edited_and_deleted(): void
    {
        $message = $this->chat->messages()->create([
            'chat_agent_id' => $this->pivots['alpha']->id,
            'agent_id' => $this->pivots['alpha']->agent_id,
            'content' => 'original',
        ]);

        $this->putJson(route('messages.update', $message), ['content' => 'edited'])
            ->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('edited', $message->refresh()->content);

        $this->deleteJson(route('messages.destroy', $message))
            ->assertOk()->assertJsonPath('ok', true);
        $this->assertModelMissing($message);
    }

    public function test_note_does_not_reset_round_robin(): void
    {
        Http::fake([
            'api.gamma.test/*' => Http::response([
                'choices' => [['message' => ['content' => 'From Gamma']]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5],
                'model' => 'gamma-model',
            ]),
        ]);

        // Beta spoke last; a note follows. The next speaker must be Gamma (pos 3), not Alpha.
        $this->chat->messages()->create([
            'chat_agent_id' => $this->pivots['beta']->id,
            'agent_id' => $this->pivots['beta']->agent_id,
            'content' => 'Beta speaking.',
        ]);
        $this->chat->messages()->create([
            'type' => 'note',
            'target_chat_agent_id' => $this->pivots['gamma']->id,
            'content' => 'Gamma, your turn.',
        ]);

        (new ProcessTurnJob($this->chat->id, manual: true))->handle(app(AiClient::class), app(ContextBuilder::class));

        $this->assertSame($this->pivots['gamma']->id, $this->chat->messages()->orderByDesc('id')->first()->chat_agent_id);
    }

    public function test_notes_do_not_consume_auto_budget(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => 'ok']]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5],
                'model' => 'm',
            ]),
        ]);

        $this->chat->messages()->create([
            'type' => 'note',
            'content' => 'note one',
        ]);
        $this->chat->messages()->create([
            'type' => 'note',
            'content' => 'note two',
        ]);

        $this->assertSame(2, $this->chat->messageCount());
        $this->assertSame(0, $this->chat->agentMessageCount());

        // Auto-run start with budget 2 must allow 2 *agent* messages (notes excluded).
        $this->chat->update(['auto_budget' => 2]);
        $response = $this->postJson(route('chats.start', $this->chat));
        $response->assertOk();
        $this->assertSame(2, $this->chat->refresh()->message_limit);
        $this->assertSame('idle', $this->chat->status, 'Sync queue ran the loop to budget already');
    }

    public function test_display_array_carries_note_metadata(): void
    {
        $this->chat->messages()->create([
            'type' => 'note',
            'target_chat_agent_id' => $this->pivots['beta']->id,
            'content' => 'psst',
        ]);
        $this->chat->messages()->create([
            'type' => 'note',
            'content' => 'hello all',
        ]);

        $messages = $this->chat->messages()->with('targetChatAgent')->orderBy('id')->get();

        $this->assertSame('Beta', $messages[0]->toDisplayArray()['note_target']);
        $this->assertSame('all', $messages[1]->toDisplayArray()['note_target']);
        $this->assertSame('note', $messages[1]->toDisplayArray()['type']);
    }
}
