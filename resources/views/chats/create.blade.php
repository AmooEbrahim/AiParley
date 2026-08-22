@extends('layouts.app')

@section('title', 'New chat · AiParley')

@section('content')
    <div class="mb-6">
        <a href="{{ route('chats.index') }}" class="text-sm text-zinc-500 hover:text-zinc-300">&larr; Back to chats</a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">New chat</h1>
        <p class="mt-1 text-sm text-zinc-500">
            Add as many participants as you like — the same agent can join more than once with its own prompt and color. Position controls the round-robin order.
        </p>
    </div>

    @if ($agents->isEmpty())
        <div class="card p-12 text-center text-sm text-zinc-400">
            You need at least one agent first.
            <a href="{{ route('agents.index') }}" class="text-violet-400 hover:underline">Create an agent &rarr;</a>
        </div>
    @else
        <form method="POST" action="{{ route('chats.store') }}" class="space-y-6"
              x-data="{
                  agents: @js($agents->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'model' => $a->model, 'color' => $a->color])),
                  rows: @js(collect(old('participants', []))->values()->map(fn ($r) => [
                      'agent_id' => $r['agent_id'] ?? '',
                      'display_name' => $r['display_name'] ?? '',
                      'position' => $r['position'] ?? null,
                      'initial_prompt' => $r['initial_prompt'] ?? '',
                      'color' => $r['color'] ?? '',
                  ])->all()),
                  add() {
                      this.rows.push({ agent_id: '', display_name: '', position: this.rows.length + 1, initial_prompt: '', color: '' })
                  },
                  remove(i) {
                      this.rows.splice(i, 1)
                      this.rows.forEach((row, idx) => { if (row.position === null || row.position === '') row.position = idx + 1 })
                  },
                  onAgentChange(row) {
                      const agent = this.agents.find((a) => a.id == row.agent_id)
                      row.color = agent ? agent.color : ''
                  },
              }">
            @csrf

            <div class="card p-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_160px]">
                    <div>
                        <label class="label">Title</label>
                        <input type="text" name="title" value="{{ old('title') }}" required
                               placeholder="e.g. Stoic meets Hedonist" class="input">
                    </div>
                    <div>
                        <label class="label">Auto budget</label>
                        <input type="number" name="auto_budget" min="1" max="500"
                               value="{{ old('auto_budget', 20) }}" class="input">
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <template x-for="(row, i) in rows" :key="i">
                    <div class="card p-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                  :style="{ background: (row.color || '#27272a') + '26', color: row.color || '#a1a1aa' }"
                                  x-text="row.agent_id ? (agents.find((a) => a.id == row.agent_id)?.name ?? '?').charAt(0) : '?'"></span>
                            <select x-model="row.agent_id" @change="onAgentChange(row)"
                                    :name="`participants[${i}][agent_id]`" required
                                    class="rounded-lg border border-zinc-700/80 bg-zinc-900/70 px-3 py-2 text-sm">
                                <option value="" disabled selected>Choose agent / model…</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }} — {{ $agent->model }}</option>
                                @endforeach
                            </select>
                            <input type="text" x-model="row.display_name" :name="`participants[${i}][display_name]`"
                                   dir="auto" maxlength="120"
                                   :placeholder="row.agent_id ? `Name (defaults to ${agents.find((a) => a.id == row.agent_id)?.name})` : 'Name'"
                                   class="input !w-44 flex-none !py-2 text-sm">
                            <input type="color" x-model="row.color" :name="`participants[${i}][color]`"
                                   title="Bubble color for this participant"
                                   class="h-[38px] w-14 cursor-pointer rounded-lg border border-zinc-700 bg-zinc-900 p-1">
                            <label class="flex items-center gap-2 text-xs text-zinc-500">
                                Position
                                <input type="number" x-model.number="row.position" :name="`participants[${i}][position]`"
                                       min="0" class="w-16 rounded-lg border border-zinc-700/80 bg-zinc-900/70 px-2 py-1.5 text-sm">
                            </label>
                            <button type="button" @click="remove(i)"
                                    class="ml-auto rounded-lg border border-zinc-700 p-2 text-zinc-500 transition hover:border-red-500/40 hover:text-red-400"
                                    title="Remove participant">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.14l.25 6a.75.75 0 0 0 1.5-.14l-.25-6Zm3.5.02a.75.75 0 0 0-.72.78l.2 6a.75.75 0 1 0 1.5-.05l-.2-6a.75.75 0 0 0-.78-.73Z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                        <div class="mt-3">
                            <label class="label">Initial prompt for this chat <span class="text-zinc-600">(optional — falls back to the agent default)</span></label>
                            <textarea x-model="row.initial_prompt" :name="`participants[${i}][initial_prompt]`" rows="2"
                                      class="input font-mono text-[13px]" placeholder="Who is this participant in the conversation?"></textarea>
                        </div>
                    </div>
                </template>

                <button type="button" @click="add()"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-zinc-700 py-4 text-sm font-medium text-zinc-400 transition hover:border-violet-500/50 hover:text-violet-300">
                    + Add participant
                </button>

                @error('participants')
                    <p class="text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-primary" :disabled="rows.length === 0">Create chat</button>
        </form>
    @endif
@endsection
