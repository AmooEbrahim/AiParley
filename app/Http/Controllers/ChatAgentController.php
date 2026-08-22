<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Chat;
use App\Models\ChatAgent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChatAgentController extends Controller
{
    public function store(Request $request, Chat $chat)
    {
        $data = $request->validate([
            'agent_id' => ['required', 'integer', Rule::exists('agents', 'id')->whereNull('deleted_at')],
            'display_name' => ['nullable', 'string', 'max:120'],
            'initial_prompt' => ['nullable', 'string', 'max:20000'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $agent = Agent::find($data['agent_id']);

        // The same agent may be added multiple times — each entry gets its own pivot.
        $chat->chatAgents()->create([
            'agent_id' => $agent->id,
            'color' => $data['color'] ?? $agent->color,
            'display_name' => $data['display_name'] ?? null,
            'position' => (int) $chat->chatAgents()->max('position') + 1,
            'initial_prompt' => $data['initial_prompt'] ?? null,
            'active' => true,
            'joined_at' => now(),
        ]);

        return back()->with('success', 'Agent added to chat.');
    }

    public function update(Request $request, Chat $chat, ChatAgent $chatAgent)
    {
        $data = $request->validate([
            'agent_id' => ['nullable', 'integer', Rule::exists('agents', 'id')->whereNull('deleted_at')],
            'display_name' => ['nullable', 'string', 'max:120'],
            'position' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'initial_prompt' => ['nullable', 'string', 'max:20000'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $swapId = isset($data['agent_id']) && (int) $data['agent_id'] !== $chatAgent->agent_id
            ? (int) $data['agent_id']
            : null;

        // Swap: retire the old pivot, create a fresh one so history keeps its identity.
        if ($swapId !== null) {
            $newAgent = Agent::find($swapId);

            $chatAgent->update(['active' => false, 'left_at' => now()]);

            $chat->chatAgents()->create([
                'agent_id' => $newAgent->id,
                'color' => $data['color'] ?? $newAgent->color,
                'display_name' => array_key_exists('display_name', $data) ? $data['display_name'] : $chatAgent->display_name,
                'position' => $data['position'] ?? $chatAgent->position,
                'initial_prompt' => array_key_exists('initial_prompt', $data) ? $data['initial_prompt'] : null,
                'active' => true,
                'joined_at' => now(),
            ]);

            return back()->with('success', 'Agent swapped.');
        }

        $chatAgent->update([
            'display_name' => array_key_exists('display_name', $data) ? $data['display_name'] : $chatAgent->display_name,
            'position' => $data['position'] ?? $chatAgent->position,
            'initial_prompt' => array_key_exists('initial_prompt', $data) ? $data['initial_prompt'] : $chatAgent->initial_prompt,
            'color' => $data['color'] ?? $chatAgent->color,
        ]);

        return back()->with('success', 'Chat agent updated.');
    }

    public function destroy(Chat $chat, ChatAgent $chatAgent)
    {
        $chatAgent->update(['active' => false, 'left_at' => now()]);

        return back()->with('success', 'Agent removed from chat (history kept).');
    }
}
