@extends('layouts.app')

@section('title', $chat->title . ' · AiParley')

@section('content')
    <div x-data="chatRoom(@js($initialState))" class="flex flex-col gap-4">

        {{-- Top controls --}}
        <div class="card p-4">
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('chats.index') }}" class="text-zinc-500 hover:text-zinc-300" title="Back to chats">&larr;</a>
                <h1 class="text-lg font-semibold tracking-tight" x-text="title"></h1>
                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wider"
                      :class="{
                          'border-zinc-600/60 bg-zinc-800/60 text-zinc-400': status === 'idle',
                          'border-emerald-500/40 bg-emerald-500/10 text-emerald-300': status === 'running',
                          'border-red-500/40 bg-red-500/10 text-red-300': status === 'error',
                      }">
                    <span x-show="status === 'running'" class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"></span>
                    <span x-text="status"></span>
                </span>

                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <template x-if="status === 'idle'">
                        <button type="button" class="btn-primary"
                                :disabled="busy || !budgetValid"
                                :title="budgetValid ? 'Start the automatic loop with the current budget' : 'Budget must be between 1 and 500'"
                                x-on:click="start()">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.84A1.5 1.5 0 0 0 4 4.11v11.78a1.5 1.5 0 0 0 2.3 1.27l9.34-5.89a1.5 1.5 0 0 0 0-2.54L6.3 2.84Z"/></svg>
                            Start auto
                        </button>
                    </template>
                    <template x-if="status === 'running'">
                        <button type="button" class="btn-danger" :disabled="busy" x-on:click="stop()">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4.5A1.5 1.5 0 0 1 6.5 3h7A1.5 1.5 0 0 1 15 4.5v11a1.5 1.5 0 0 1-1.5 1.5h-7A1.5 1.5 0 0 1 5 15.5v-11Z"/></svg>
                            Stop
                        </button>
                    </template>
                    <button type="button" class="btn-ghost" :disabled="busy || status !== 'idle'" x-on:click="next()">
                        Next message &nbsp;&#9654;
                    </button>

                    <form method="POST" action="{{ route('chats.update', $chat) }}" class="flex items-center gap-2">
                        @csrf
                        @method('PUT')
                        <label for="chat-budget" class="text-xs text-zinc-500">Budget</label>
                        <input type="number" id="chat-budget" name="auto_budget" min="1" max="500" x-model="budget"
                               class="w-20 rounded-lg border border-zinc-700/80 bg-zinc-900/70 px-2 py-1.5 text-sm
                                      focus:border-violet-500/60 focus:outline-none focus:ring-2 focus:ring-violet-500/20">
                        <button type="submit" class="btn-ghost !px-3 !py-1.5" x-show="budgetDirty" x-cloak>Save</button>
                        <span x-show="budgetDirty" x-cloak class="text-[11px] text-amber-400/90">unsaved</span>
                    </form>

                    <div class="relative" x-data="{ menu: false }"
                         x-on:click.outside="menu = false" x-on:keydown.escape.window="menu = false">
                        <button type="button" class="btn-ghost !px-2.5 !py-1.5" title="More actions"
                                :aria-expanded="menu" x-on:click="menu = !menu">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/></svg>
                        </button>
                        <div x-show="menu" x-cloak x-transition.opacity
                             class="absolute right-0 z-30 mt-2 w-44 rounded-xl border border-zinc-700 bg-zinc-900 p-1 shadow-2xl">
                            <form method="POST" action="{{ route('chats.destroy', $chat) }}"
                                  onsubmit="return confirm('Delete this chat and all its messages?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs text-red-300 transition hover:bg-red-500/10">
                                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.14l.25 6a.75.75 0 0 0 1.5-.14l-.25-6Zm3.5.02a.75.75 0 0 0-.72.78l.2 6a.75.75 0 1 0 1.5-.05l-.2-6a.75.75 0 0 0-.78-.73Z" clip-rule="evenodd"/></svg>
                                    Delete chat
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-2 text-xs text-zinc-500" x-text="status === 'running'
                ? `Running — ${message_count} / ${message_limit} messages before auto-stop`
                : `Idle — ${message_count} messages, budget ${auto_budget}`"></div>

            <div x-show="actionError" x-cloak x-transition
                 class="mt-3 flex items-center justify-between gap-4 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-300">
                <span x-text="actionError"></span>
                <button x-on:click="actionError = null" class="text-amber-400/70 hover:text-amber-200">&times;</button>
            </div>

            <div x-show="status === 'error'" x-cloak x-transition
                 class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3">
                <div class="text-sm text-red-200">
                    <p class="font-semibold">Conversation stopped with an error<span x-show="error_agent" class="font-normal"> — agent: <span class="font-semibold" x-text="error_agent"></span></span></p>
                    <p class="mt-0.5 max-w-2xl break-words font-mono text-xs text-red-300/80" x-text="last_error"></p>
                </div>
                <button type="button" class="btn-ghost" :disabled="busy" x-on:click="reset()">Reset to idle</button>
            </div>
        </div>

        {{-- Agents panel --}}
        @php($activeChatAgents = $chat->chatAgents->where('active', true)->sortBy('position'))
        @php($inactiveChatAgents = $chat->chatAgents->where('active', false)->sortBy('position'))
        @php($formerNames = $inactiveChatAgents->groupBy(fn ($ca) => $ca->display_name ?: $ca->agent->name)->map->count())

        <details class="card">
            <summary class="flex cursor-pointer select-none items-center gap-2 px-4 py-3 text-sm font-semibold uppercase tracking-wider text-zinc-400 hover:text-zinc-200">
                Agents in this chat
                <span class="rounded-full border border-zinc-700/70 bg-zinc-800/50 px-2 py-0.5 text-[10px] font-medium normal-case tracking-normal text-zinc-500">
                    {{ $activeChatAgents->count() }} active
                </span>
            </summary>
            <div class="space-y-3 border-t border-zinc-800/80 p-4">
                <form id="bulk-agents-form" method="POST" action="{{ route('chat-agents.bulk', $chat) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs text-zinc-500">Edit any participant, then save all changes once.</p>
                        <button type="submit" class="btn-primary !px-3 !py-1.5">Save all</button>
                    </div>

                    <div class="space-y-3">
                        @foreach ($activeChatAgents as $chatAgent)
                            @php($pivotColor = $chatAgent->color ?: ($chatAgent->agent->color ?? '#a78bfa'))
                            <div data-participant-id="{{ $chatAgent->id }}"
                                 class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-3.5 transition focus-within:border-zinc-700">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <input type="color" name="agents[{{ $chatAgent->id }}][color]" value="{{ $pivotColor }}" title="Bubble color"
                                           class="h-9 w-12 shrink-0 cursor-pointer rounded-lg border border-zinc-700 bg-zinc-900 p-1">
                                    <input type="text" name="agents[{{ $chatAgent->id }}][display_name]"
                                           value="{{ $chatAgent->display_name }}" maxlength="120" dir="auto"
                                           placeholder="{{ $chatAgent->agent->name }}"
                                           class="min-w-[9rem] flex-1 rounded-lg border border-zinc-700/80 bg-zinc-900/70 px-3 py-1.5 text-sm font-semibold focus:border-violet-500/60 focus:outline-none focus:ring-2 focus:ring-violet-500/20"
                                           style="color: {{ $pivotColor }}">
                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-zinc-700/70 bg-zinc-800/50 px-2 py-0.5 text-[11px] text-zinc-400">
                                        {{ $chatAgent->agent->name }}
                                        <span class="text-zinc-600">·</span>
                                        <code class="font-mono text-[11px]">{{ $chatAgent->agent->model }}</code>
                                    </span>
                                    <label class="flex items-center gap-1.5 text-[11px] text-zinc-500">
                                        Position
                                        <input type="number" name="agents[{{ $chatAgent->id }}][position]" min="0"
                                               value="{{ $chatAgent->position }}"
                                               class="w-16 rounded-lg border border-zinc-700/80 bg-zinc-900/70 px-2 py-1 text-sm">
                                    </label>
                                    <label class="flex items-center gap-1.5 text-[11px] text-zinc-500">
                                        Swap to
                                        <select name="agents[{{ $chatAgent->id }}][agent_id]"
                                                class="rounded-lg border border-zinc-700/80 bg-zinc-900/70 px-2 py-1 text-sm">
                                            <option value="">— keep —</option>
                                            @foreach ($availableAgents as $availableAgent)
                                                <option value="{{ $availableAgent->id }}">{{ $availableAgent->name }} — {{ $availableAgent->model }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <button type="button"
                                            onclick="if (confirm('Remove this participant from the chat? Their messages stay in history.')) { document.getElementById('remove-agent-{{ $chatAgent->id }}').submit(); }"
                                            class="ml-auto rounded-md px-2 py-1 text-[11px] text-zinc-500 transition hover:bg-red-500/10 hover:text-red-300"
                                            title="Remove from chat (history kept)">Remove</button>
                                </div>
                                <div class="mt-3">
                                    <label for="initial-prompt-{{ $chatAgent->id }}" class="label">Initial prompt for this chat</label>
                                    <textarea id="initial-prompt-{{ $chatAgent->id }}"
                                              name="agents[{{ $chatAgent->id }}][initial_prompt]" rows="4" dir="auto"
                                              placeholder="Who is this participant in this conversation?"
                                              class="input min-h-[110px] resize-y text-sm leading-relaxed">{{ $chatAgent->initial_prompt }}</textarea>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </form>

                @foreach ($activeChatAgents as $chatAgent)
                    <form id="remove-agent-{{ $chatAgent->id }}" method="POST"
                          action="{{ route('chat-agents.destroy', [$chat, $chatAgent]) }}" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach

                @if ($inactiveChatAgents->isNotEmpty())
                    <details class="rounded-xl border border-zinc-800/70 bg-zinc-900/30">
                        <summary class="cursor-pointer select-none px-3 py-2 text-[11px] text-zinc-500 hover:text-zinc-300">
                            Former participants <span class="text-zinc-600">· history kept</span>
                        </summary>
                        <div class="flex flex-wrap gap-1.5 border-t border-zinc-800/70 px-3 py-2">
                            @foreach ($formerNames as $name => $count)
                                <span class="inline-flex items-center gap-1.5 rounded-md border border-zinc-800 px-2 py-1 text-[11px] text-zinc-500">
                                    {{ $name }}@if ($count > 1)<span class="text-zinc-600">×{{ $count }}</span>@endif
                                </span>
                            @endforeach
                        </div>
                    </details>
                @endif

                <div class="rounded-xl border border-dashed border-zinc-700/70 bg-zinc-900/30 p-3.5"
                     x-data="{ color: '{{ $availableAgents->first()?->color ?? '#a78bfa' }}' }">
                    <form method="POST" action="{{ route('chat-agents.store', $chat) }}" class="space-y-3">
                        @csrf
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="min-w-[220px] flex-1">
                                <label for="add-agent-id" class="label">Add agent</label>
                                <select id="add-agent-id" name="agent_id" required
                                        @change="const o = $event.target.selectedOptions[0]; if (o?.dataset.color) color = o.dataset.color"
                                        class="w-full rounded-lg border border-zinc-700/80 bg-zinc-900/70 px-2 py-2 text-sm">
                                    <option value="" disabled selected>Choose agent / model…</option>
                                    @foreach ($availableAgents as $availableAgent)
                                        <option value="{{ $availableAgent->id }}" data-color="{{ $availableAgent->color }}">{{ $availableAgent->name }} — {{ $availableAgent->model }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="min-w-[160px]">
                                <label for="add-agent-name" class="label">Name <span class="text-zinc-600">(optional)</span></label>
                                <input type="text" id="add-agent-name" name="display_name" maxlength="120" dir="auto"
                                       class="w-full rounded-lg border border-zinc-700/80 bg-zinc-900/70 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label for="add-agent-color" class="label">Color</label>
                                <input type="color" id="add-agent-color" name="color" x-model="color" title="Bubble color"
                                       class="h-[38px] w-14 cursor-pointer rounded-lg border border-zinc-700 bg-zinc-900 p-1">
                            </div>
                        </div>
                        <div>
                            <label for="add-agent-prompt" class="label">Initial prompt <span class="text-zinc-600">(optional — falls back to the agent default)</span></label>
                            <textarea id="add-agent-prompt" name="initial_prompt" rows="3" dir="auto"
                                      class="input min-h-[80px] resize-y text-sm leading-relaxed"
                                      placeholder="Who is this participant in this conversation?"></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="btn-ghost !px-3 !py-1.5">Add to chat</button>
                        </div>
                    </form>
                </div>
            </div>
        </details>

        {{-- Messages --}}
        <div class="card relative flex-1 p-4">
            <button type="button" x-show="unread > 0" x-cloak x-transition
                    @click="scrollToBottom()"
                    class="absolute bottom-8 left-1/2 z-10 flex -translate-x-1/2 items-center gap-2 rounded-full border border-violet-500/40 bg-violet-600 px-4 py-2 text-xs font-semibold text-white shadow-lg shadow-violet-900/40 transition hover:bg-violet-500">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-white/20 text-[11px]"
                      x-text="unread"></span>
                <span x-text="unread === 1 ? 'new message' : 'new messages'"></span>
                <span aria-hidden="true">&darr;</span>
            </button>

            <div x-ref="list" @scroll.passive="onScroll"
                 class="h-[68vh] min-h-[280px] space-y-4 overflow-y-auto scroll-smooth pr-1">
                <template x-if="messages.length === 0">
                    <div class="flex h-[220px] flex-col items-center justify-center gap-2 text-center">
                        <p class="text-sm text-zinc-500">No messages yet.</p>
                        <p class="text-xs text-zinc-600">Press <span class="font-semibold text-zinc-400">Next message</span> for one turn, or <span class="font-semibold text-zinc-400">Start auto</span> to let them loose.</p>
                    </div>
                </template>

                <template x-for="m in messages" :key="m.id">
                    <div class="group rounded-2xl border p-4 transition"
                         :class="m.type === 'note'
                            ? 'border-dashed border-amber-500/40 bg-amber-500/5'
                            : 'border-zinc-800 hover:brightness-110'"
                         :style="m.type === 'note' ? {} : { borderColor: m.agent_color + '55', background: m.agent_color + '0d' } }">
                        <div class="flex items-center gap-2.5">
                            <template x-if="m.type === 'note'">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-xs text-amber-400" aria-hidden="true">&#127916;</span>
                            </template>
                            <template x-if="m.type !== 'note'">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                      :style="{ background: m.agent_color + '26', color: m.agent_color }"
                                      x-text="(m.agent_name || '?').charAt(0)"></span>
                            </template>

                            <span class="text-sm font-semibold"
                                  :class="m.type === 'note' ? 'text-amber-300' : ''"
                                  :style="m.type === 'note' ? {} : { color: m.agent_color }"
                                  x-text="m.type === 'note' ? 'Director note' : m.agent_name"></span>
                            <span x-show="m.type === 'note'" class="text-[11px] text-amber-400/80"
                                  x-text="'&rarr; ' + (m.note_target ?? 'all')"></span>
                            <span x-show="m.type === 'impersonated'" class="text-[10px] text-zinc-500" title="Written by you">&#9998; written</span>
                            <span class="font-mono text-[11px] text-zinc-500" x-show="m.type !== 'note'" x-text="m.model"></span>

                            <span class="ml-auto flex items-center gap-1">
                                <span class="text-[11px] text-zinc-600" x-text="fmtTime(m.created_at)"></span>
                                <button type="button" @click="editMsg(m)" title="Edit message"
                                        class="rounded-md p-1.5 text-zinc-600 opacity-0 transition hover:bg-zinc-700/60 hover:text-zinc-200 group-hover:opacity-100">
                                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z" /><path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z" /></svg>
                                </button>
                                <button type="button" @click="removeMsg(m.id)" title="Delete message"
                                        class="rounded-md p-1.5 text-zinc-600 opacity-0 transition hover:bg-red-500/10 hover:text-red-400 group-hover:opacity-100">
                                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.14l.25 6a.75.75 0 0 0 1.5-.14l-.25-6Zm3.5.02a.75.75 0 0 0-.72.78l.2 6a.75.75 0 1 0 1.5-.05l-.2-6a.75.75 0 0 0-.78-.73Z" clip-rule="evenodd" /></svg>
                                </button>
                            </span>
                        </div>

                        <template x-if="editingId === m.id">
                            <div class="mt-2">
                                <textarea x-model="draftEdit" rows="4" dir="auto"
                                          class="input font-mono text-[14px]"></textarea>
                                <div class="mt-2 flex items-center gap-2">
                                    <button type="button" class="btn-primary !px-3 !py-1.5" :disabled="busy" @click="saveEdit()">Save</button>
                                    <button type="button" class="btn-ghost !px-3 !py-1.5" @click="editingId = null">Cancel</button>
                                </div>
                            </div>
                        </template>
                        <template x-if="editingId !== m.id">
                            <p class="mt-2 whitespace-pre-wrap text-[15px] leading-relaxed text-zinc-100" dir="auto" x-text="m.content"></p>
                        </template>

                        <div class="mt-2.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-zinc-500">
                            <template x-if="m.latency_ms !== null"><span>&#9889; <span x-text="fmtMs(m.latency_ms)"></span></span></template>
                            <template x-if="m.prompt_tokens !== null"><span>&uarr;<span x-text="fmtTokens(m.prompt_tokens)"></span></span></template>
                            <template x-if="m.completion_tokens !== null"><span>&darr;<span x-text="fmtTokens(m.completion_tokens)"></span> tok</span></template>
                            <template x-if="m.cost !== null"><span x-text="fmtCost(m.cost)"></span></template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Composer: send as an agent, or a director note to one/all participants --}}
            <div class="mt-4 border-t border-zinc-800/80 pt-3">
                <button type="button" x-show="!composerOpen" @click="composerOpen = true"
                        class="flex items-center gap-2 rounded-lg border border-dashed border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-400 transition hover:border-violet-500/50 hover:text-violet-300">
                    + Add message
                </button>

                <div x-show="composerOpen" x-cloak x-transition class="space-y-3">
                    <textarea x-model="draft" rows="3" dir="auto"
                              class="input text-[15px]"
                              placeholder="Write a message as one of the agents, or a director note for the assistant(s)…"></textarea>
                    <div class="flex flex-wrap items-center gap-3">
                        <select x-model="composerKind" class="rounded-lg border border-zinc-700/80 bg-zinc-900/70 px-3 py-2 text-sm">
                            <option value="" disabled selected>Send as…</option>
                            @foreach ($chat->chatAgents->where('active', true)->sortBy('position') as $p)
                                <option value="{{ $p->id }}">As {{ $p->display_name ?: $p->agent->name }}</option>
                            @endforeach
                            <option value="note">Director note (to assistant)</option>
                        </select>

                        <select x-show="composerKind === 'note'" x-model="composerTarget" x-cloak
                                class="rounded-lg border border-zinc-700/80 bg-zinc-900/70 px-3 py-2 text-sm">
                            <option value="">All agents</option>
                            @foreach ($chat->chatAgents->where('active', true)->sortBy('position') as $p)
                                <option value="{{ $p->id }}">Only {{ $p->display_name ?: $p->agent->name }}</option>
                            @endforeach
                        </select>

                        <span class="ml-auto flex items-center gap-2">
                            <button type="button" class="btn-ghost" @click="composerOpen = false; draft = ''">Cancel</button>
                            <button type="button" class="btn-primary" :disabled="busy || !draft.trim() || !composerKind" @click="sendCustom()">Send</button>
                        </span>
                    </div>
                </div>
            </div>

            {{-- Stats footer --}}
            <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-1 border-t border-zinc-800/80 pt-3 text-xs text-zinc-500">
                <span><span class="font-semibold text-zinc-300" x-text="stats.total_messages"></span> messages</span>
                <span><span class="font-semibold text-zinc-300" x-text="fmtTokens(stats.total_tokens) ?? '0'"></span> tokens</span>
                <span>avg <span class="font-semibold text-zinc-300" x-text="fmtMs(stats.avg_latency_ms)"></span></span>
                <span>cost <span class="font-semibold text-zinc-300" x-text="fmtCost(stats.cost) ?? '$0'"></span></span>
            </div>
        </div>
    </div>
@endsection
