<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\ChatAgent;
use App\Services\AiClient;
use App\Services\ContextBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ProcessTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 15, 30];

    public int $timeout = 360;

    public bool $manual;

    public ?int $currentChatAgentId = null;

    public function __construct(public int $chatId, bool $manual = false)
    {
        $this->manual = $manual;
    }

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            // One in-flight turn per chat; queued turns wait 5s and retry.
            (new WithoutOverlapping("chat:{$this->chatId}", 5, 600)),
        ];
    }

    public function handle(AiClient $client, ContextBuilder $contextBuilder): void
    {
        $chat = Chat::find($this->chatId);

        if ($chat === null) {
            return;
        }

        // Auto turns only run while the chat is running; manual turns only while idle.
        if (! $this->manual && $chat->status !== 'running') {
            return;
        }

        if ($this->manual && $chat->status !== 'idle') {
            return;
        }

        if ($this->budgetReached($chat)) {
            $chat->update(['status' => 'idle']);

            return;
        }

        $speaker = $this->nextSpeaker($chat);

        if ($speaker === null) {
            $chat->update(['status' => 'idle', 'last_error' => 'No active agents in this chat.']);

            return;
        }

        $this->currentChatAgentId = $speaker->id;

        $agent = $speaker->agent;

        $messages = $contextBuilder->build($speaker);

        $start = microtime(true);
        $result = $client->complete($agent, $messages);
        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        $chat->messages()->create([
            'chat_agent_id' => $speaker->id,
            'agent_id' => $agent->id,
            'content' => $result['content'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'cost' => $agent->priceFor((int) ($result['prompt_tokens'] ?? 0), (int) ($result['completion_tokens'] ?? 0)),
            'latency_ms' => $latencyMs,
            'model' => $result['model'],
        ]);

        if ($this->manual) {
            return;
        }

        $chat->refresh();

        if ($chat->status !== 'running') {
            return;
        }

        if ($this->budgetReached($chat)) {
            $chat->update(['status' => 'idle']);

            return;
        }

        static::dispatch($this->chatId)->delay(now()->addSeconds(2));
    }

    public function failed(?\Throwable $e): void
    {
        $chat = Chat::find($this->chatId);

        if ($chat === null) {
            return;
        }

        $chat->update([
            'status' => 'error',
            'last_error' => $e?->getMessage() ?? 'Unknown error.',
            'error_agent_id' => $this->currentChatAgentId,
        ]);
    }

    private function budgetReached(Chat $chat): bool
    {
        return $chat->message_limit !== null && $chat->agentMessageCount() >= $chat->message_limit;
    }

    private function nextSpeaker(Chat $chat): ?ChatAgent
    {
        $active = $chat->activeChatAgents()->with('agent')->get();

        if ($active->isEmpty()) {
            return null;
        }

        // Notes (chat_agent_id = null) must not reset the round-robin.
        $lastMessage = $chat->messages()
            ->whereNotNull('chat_agent_id')
            ->orderByDesc('id')
            ->first(['id', 'chat_agent_id']);

        if ($lastMessage === null) {
            return $active->first();
        }

        $lastPosition = ChatAgent::query()->whereKey($lastMessage->chat_agent_id)->value('position');

        if ($lastPosition === null) {
            return $active->first();
        }

        return $active->first(fn (ChatAgent $ca) => $ca->position > $lastPosition) ?? $active->first();
    }
}
