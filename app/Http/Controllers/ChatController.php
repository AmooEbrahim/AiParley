<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTurnJob;
use App\Models\Agent;
use App\Models\Chat;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChatController extends Controller
{
    public function index(): View
    {
        $chats = Chat::query()
            ->with(['chatAgents.agent'])
            ->withCount('messages')
            ->latest()
            ->get();

        return view('chats.index', ['chats' => $chats]);
    }

    public function create(): View
    {
        return view('chats.create', [
            'agents' => Agent::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'auto_budget' => ['nullable', 'integer', 'min:1', 'max:500'],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.agent_id' => ['required', 'integer', Rule::exists('agents', 'id')->whereNull('deleted_at')],
            'participants.*.display_name' => ['nullable', 'string', 'max:120'],
            'participants.*.position' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'participants.*.initial_prompt' => ['nullable', 'string', 'max:20000'],
            'participants.*.color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $chat = Chat::create([
            'title' => $data['title'],
            'auto_budget' => $data['auto_budget'] ?? 20,
        ]);

        $this->attachParticipants($chat, $data['participants']);

        return redirect()->route('chats.show', $chat)->with('success', 'Chat created.');
    }

    public function show(Chat $chat): View
    {
        $chat->load(['chatAgents.agent' => fn ($q) => $q->withTrashed()]);

        return view('chats.show', [
            'chat' => $chat,
            'availableAgents' => Agent::query()->orderBy('name')->get(),
            'initialState' => $this->stateFor($chat),
        ]);
    }

    public function update(Request $request, Chat $chat): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'auto_budget' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $chat->update($data);

        return redirect()->route('chats.show', $chat)->with('success', 'Chat updated.');
    }

    public function destroy(Chat $chat): RedirectResponse
    {
        $chat->delete();

        return redirect()->route('chats.index')->with('success', 'Chat deleted.');
    }

    public function start(Chat $chat)
    {
        if ($chat->status !== 'idle') {
            return response()->json(['ok' => false, 'error' => 'Chat must be idle to start auto-run.'], 409);
        }

        if ($chat->activeChatAgents()->count() < 2) {
            return response()->json(['ok' => false, 'error' => 'Auto-run needs at least 2 active agents.'], 422);
        }

        $chat->update([
            'status' => 'running',
            'message_limit' => $chat->agentMessageCount() + $chat->auto_budget,
            'last_error' => null,
            'error_agent_id' => null,
        ]);

        ProcessTurnJob::dispatch($chat->id);

        return response()->json(['ok' => true]);
    }

    public function stop(Chat $chat)
    {
        if ($chat->status === 'running') {
            $chat->update(['status' => 'idle']);
        }

        return response()->json(['ok' => true]);
    }

    public function next(Chat $chat)
    {
        if ($chat->status !== 'idle') {
            return response()->json(['ok' => false, 'error' => 'Next message is only available when the chat is idle.'], 409);
        }

        if ($chat->activeChatAgents()->count() < 1) {
            return response()->json(['ok' => false, 'error' => 'This chat has no active agents.'], 422);
        }

        ProcessTurnJob::dispatch($chat->id, manual: true);

        return response()->json(['ok' => true]);
    }

    public function reset(Chat $chat)
    {
        $chat->update([
            'status' => 'idle',
            'last_error' => null,
            'error_agent_id' => null,
        ]);

        return response()->json(['ok' => true]);
    }

    public static function stateFor(Chat $chat): array
    {
        $chat->refresh();

        return [
            'id' => $chat->id,
            'title' => $chat->title,
            'status' => $chat->status,
            'auto_budget' => $chat->auto_budget,
            'message_limit' => $chat->message_limit,
            'message_count' => $chat->agentMessageCount(),
            'last_error' => $chat->last_error,
            'error_agent' => $chat->chatAgents->firstWhere('id', $chat->error_agent_id)?->displayName(),
            'stats' => $chat->stats(),
            'messages' => $chat->messages()
                ->with(['agent', 'chatAgent', 'targetChatAgent'])
                ->orderBy('id')
                ->get()
                ->map(fn ($m) => $m->toDisplayArray())
                ->values()
                ->all(),
        ];
    }

    private function attachParticipants(Chat $chat, array $participants): void
    {
        // Same agent may appear multiple times — each entry is its own pivot.
        $agents = Agent::whereIn('id', array_column($participants, 'agent_id'))->get()->keyBy('id');

        foreach (array_values($participants) as $index => $participant) {
            $agent = $agents->get($participant['agent_id']);

            $chat->chatAgents()->create([
                'agent_id' => $agent->id,
                'color' => $participant['color'] ?? $agent->color,
                'display_name' => $participant['display_name'] ?? null,
                'position' => (int) ($participant['position'] ?? $index + 1),
                'initial_prompt' => $participant['initial_prompt'] ?? null,
                'active' => true,
                'joined_at' => now(),
            ]);
        }
    }
}
