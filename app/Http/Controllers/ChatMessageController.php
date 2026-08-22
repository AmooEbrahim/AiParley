<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChatMessageController extends Controller
{
    /**
     * Polling endpoint: returns messages newer than `after` plus live chat state.
     */
    public function index(Request $request, Chat $chat)
    {
        $after = (int) $request->query('after', 0);

        $messages = $chat->messages()
            ->with(['agent', 'chatAgent', 'targetChatAgent'])
            ->where('id', '>', $after)
            ->orderBy('id')
            ->get()
            ->map(fn ($m) => $m->toDisplayArray())
            ->values();

        return response()->json([
            'messages' => $messages,
            'status' => $chat->status,
            'message_limit' => $chat->message_limit,
            'message_count' => $chat->agentMessageCount(),
            'last_error' => $chat->last_error,
            'error_agent' => $chat->error_agent_id
                ? $chat->chatAgents()->find($chat->error_agent_id)?->displayName()
                : null,
            'stats' => $chat->stats(),
        ]);
    }

    /**
     * User-composed messages: either "as an agent" (impersonation, takes a
     * round-robin slot like any agent message) or a director note addressed
     * to one participant or all of them.
     */
    public function store(Request $request, Chat $chat)
    {
        $pivotRule = Rule::exists('chat_agents', 'id')
            ->where('chat_id', $chat->id)
            ->where('active', true);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:50000'],
            'kind' => ['required', 'in:agent,note'],
            'chat_agent' => ['nullable', 'integer', $pivotRule],
            'target' => ['nullable', 'integer', $pivotRule],
        ]);

        if ($data['kind'] === 'agent') {
            if (empty($data['chat_agent'])) {
                return response()->json(['ok' => false, 'error' => 'Pick which agent to send as.'], 422);
            }

            $pivot = $chat->chatAgents()->find($data['chat_agent']);

            $chat->messages()->create([
                'chat_agent_id' => $pivot->id,
                'agent_id' => $pivot->agent_id,
                'type' => 'impersonated',
                'content' => $data['content'],
            ]);

            return response()->json(['ok' => true]);
        }

        $chat->messages()->create([
            'chat_agent_id' => null,
            'agent_id' => null,
            'type' => 'note',
            'target_chat_agent_id' => $data['target'] ?? null,
            'content' => $data['content'],
        ]);

        return response()->json(['ok' => true]);
    }

    public function update(Request $request, Message $message)
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:50000'],
        ]);

        $message->update(['content' => $data['content']]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Message $message)
    {
        $message->delete();

        return response()->json(['ok' => true]);
    }
}
