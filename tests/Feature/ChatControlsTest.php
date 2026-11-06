<?php

namespace Tests\Feature;

use App\Jobs\ProcessTurnJob;
use App\Models\Agent;
use App\Models\Chat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChatControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_auto_uses_and_saves_the_budget_from_the_request(): void
    {
        Queue::fake();

        $chat = $this->chatWithTwoAgents();
        $chat->update(['auto_budget' => 20]);

        $this->postJson(route('chats.start', $chat), ['auto_budget' => 7])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $chat->refresh();

        $this->assertSame(7, $chat->auto_budget);
        $this->assertSame(7, $chat->message_limit);
        $this->assertSame('running', $chat->status);
        Queue::assertPushed(ProcessTurnJob::class);
    }

    public function test_start_auto_rejects_an_out_of_range_budget(): void
    {
        Queue::fake();

        $chat = $this->chatWithTwoAgents();

        $this->postJson(route('chats.start', $chat), ['auto_budget' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('auto_budget');

        $this->assertSame('idle', $chat->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_all_participants_can_be_saved_in_one_request(): void
    {
        $chat = $this->chatWithTwoAgents();
        $pivots = $chat->chatAgents()->orderBy('position')->get();

        $this->put(route('chat-agents.bulk', $chat), [
            'agents' => [
                $pivots[0]->id => [
                    'display_name' => 'First',
                    'position' => 3,
                    'initial_prompt' => 'Prompt one',
                    'color' => '#112233',
                    'agent_id' => '',
                ],
                $pivots[1]->id => [
                    'display_name' => 'Second',
                    'position' => 1,
                    'initial_prompt' => 'Prompt two',
                    'color' => '#445566',
                    'agent_id' => '',
                ],
            ],
        ])->assertRedirect();

        $pivots[0]->refresh();
        $pivots[1]->refresh();

        $this->assertSame('First', $pivots[0]->display_name);
        $this->assertSame(3, $pivots[0]->position);
        $this->assertSame('Prompt one', $pivots[0]->initial_prompt);
        $this->assertSame('#112233', $pivots[0]->color);

        $this->assertSame('Second', $pivots[1]->display_name);
        $this->assertSame(1, $pivots[1]->position);
        $this->assertSame('Prompt two', $pivots[1]->initial_prompt);
        $this->assertSame('#445566', $pivots[1]->color);
    }

    public function test_bulk_save_can_swap_one_participant(): void
    {
        $chat = $this->chatWithTwoAgents();
        $pivot = $chat->chatAgents()->orderBy('position')->first();
        $replacement = $this->makeAgent('Replacement');

        $this->put(route('chat-agents.bulk', $chat), [
            'agents' => [
                $pivot->id => [
                    'display_name' => 'Swapped',
                    'position' => 1,
                    'initial_prompt' => 'New prompt',
                    'color' => '#778899',
                    'agent_id' => $replacement->id,
                ],
            ],
        ])->assertRedirect();

        $this->assertFalse($pivot->fresh()->active);

        $newPivot = $chat->chatAgents()
            ->where('active', true)
            ->where('agent_id', $replacement->id)
            ->sole();

        $this->assertSame('Swapped', $newPivot->display_name);
        $this->assertSame('New prompt', $newPivot->initial_prompt);
    }

    private function chatWithTwoAgents(): Chat
    {
        $chat = Chat::create(['title' => 'Controls chat']);

        foreach ([1, 2] as $position) {
            $chat->chatAgents()->create([
                'agent_id' => $this->makeAgent("Agent {$position}")->id,
                'position' => $position,
                'active' => true,
                'joined_at' => now(),
            ]);
        }

        return $chat;
    }

    private function makeAgent(string $name): Agent
    {
        return Agent::create([
            'name' => $name,
            'color' => '#a78bfa',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'timeout_seconds' => 30,
        ]);
    }
}
