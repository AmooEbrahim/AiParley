@extends('layouts.app')

@section('title', 'Agents · AiParley')

@section('content')
    @php($formOpen = $agents->isEmpty() || request()->old() !== [])

    <div x-data="{ newOpen: @js($formOpen) }">
        {{-- Header --}}
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Agents</h1>
                <p class="mt-1 text-sm text-zinc-500">Each agent is an AI persona — pick a model from any provider group, or wire a custom endpoint.</p>
            </div>
            <button type="button" class="btn-primary shrink-0"
                    x-bind:class="newOpen ? 'btn-ghost' : 'btn-primary'"
                    x-on:click="newOpen = !newOpen"
                    x-text="newOpen ? 'Close form' : '+ New agent'"></button>
        </div>

        {{-- ===================== Providers ===================== --}}
        <section class="mb-8 rounded-2xl border border-zinc-800/80 bg-zinc-900/50 p-4"
                 x-data="{
                     modal: null,
                     keyVisible: false,
                     confirmingDelete: null,
                     storeUrl: @js(route('providers.store')),
                     providersUrl: @js(url('providers')),
                 }"
                 x-on:keydown.escape.window="if (modal) { modal = null }; confirmingDelete = null">

            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-400">
                    Providers
                    <span class="ml-1 normal-case tracking-normal text-zinc-600">
                        {{ count($providers) }} saved · {{ count($presets) }} templates
                    </span>
                </h2>
                <button type="button" class="btn-primary !px-3 !py-1.5 text-xs"
                        x-on:click="keyVisible = false; modal = { id: null, name: '', base_url: '', has_key: false, from_template: false }">
                    + Add custom provider
                </button>
            </div>

            {{-- Saved providers --}}
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($providers as $p)
                    @php($host = parse_url($p['base_url'], PHP_URL_HOST) ?: $p['base_url'])
                    <div class="flex min-w-0 items-center gap-2.5 rounded-xl border border-zinc-700/70 bg-zinc-800/40 px-3 py-2 transition hover:border-zinc-600 hover:bg-zinc-800/70"
                         data-provider-id="{{ $p['id'] }}"
                         x-data="{ refreshing: false, count: {{ (int) $p['models_count'] }}, error: null }">
                        <span class="h-2 w-2 shrink-0 rounded-full {{ $p['has_key'] ? 'bg-emerald-400' : 'bg-amber-400' }}"
                              title="{{ $p['has_key'] ? 'API key saved' : 'No API key' }}"></span>

                        <button type="button" class="min-w-0 flex-1 text-left" title="Configure this provider"
                                x-on:click="keyVisible = false; modal = @js(['id' => $p['id'], 'name' => $p['name'], 'base_url' => $p['base_url'], 'has_key' => $p['has_key']])">
                            <div class="truncate text-sm font-medium text-zinc-100">{{ $p['name'] }}</div>
                            <div class="truncate text-[11px] text-zinc-500">
                                <span x-text="count"></span> model{{ $p['models_count'] === 1 ? '' : 's' }}
                                @if ($p['agents_count'] > 0)
                                    · {{ $p['agents_count'] }} agent{{ $p['agents_count'] === 1 ? '' : 's' }}
                                @endif
                                · <span class="font-mono">{{ $host }}</span>
                            </div>
                        </button>

                        <span class="ml-auto flex shrink-0 items-center gap-0.5 text-zinc-500">
                            <button type="button" class="rounded-md p-1.5 transition hover:bg-zinc-700 hover:text-zinc-200 disabled:pointer-events-none disabled:opacity-50 {{ $p['models_cached_at'] ? '' : 'animate-pulse text-violet-300' }}"
                                    :disabled="refreshing" title="{{ $p['models_cached_at'] ? 'Refresh model list' : 'Fetch model list' }}"
                                    x-on:click="
                                        if (refreshing) return;
                                        refreshing = true; error = null;
                                        fetch('{{ route('providers.models.refresh', ['provider' => $p['id']]) }}', {
                                            method: 'POST',
                                            headers: {
                                                Accept: 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                            },
                                        })
                                            .then((r) => r.json())
                                            .then((d) => {
                                                if (d.ok) { count = d.count; } else { error = d.error || 'Fetch failed.'; }
                                            })
                                            .catch(() => { error = 'Request failed.'; })
                                            .finally(() => { refreshing = false; })">
                                <svg x-show="!refreshing" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                                <svg x-show="refreshing" x-cloak class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-9-9"/></svg>
                            </button>

                            <button type="button" class="rounded-md p-1.5 transition hover:bg-zinc-700 hover:text-zinc-200"
                                    title="Edit / configure" x-on:click="keyVisible = false; modal = @js(['id' => $p['id'], 'name' => $p['name'], 'base_url' => $p['base_url'], 'has_key' => $p['has_key']])">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 1-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>
                            </button>

                            <button type="button" class="rounded-md p-1.5 transition hover:bg-red-500/10 hover:text-red-300"
                                    title="Delete" x-on:click="confirmingDelete = @js(['id' => $p['id'], 'name' => $p['name'], 'agents_count' => $p['agents_count']])">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                            </button>
                        </span>

                        <span x-show="error" x-cloak class="shrink-0 text-amber-400" title="Last fetch failed">⚠</span>
                        <div x-show="error" x-cloak class="sr-only" x-text="error"></div>
                    </div>
                @empty
                    <p class="col-span-full text-sm text-zinc-500">No saved providers yet — start from a template below.</p>
                @endforelse
            </div>

            {{-- Templates: a static catalog, never consumed by use or deletion --}}
            <div class="mt-3 border-t border-zinc-800/70 pt-3">
                <p class="mb-2 text-[11px] uppercase tracking-wider text-zinc-600">
                    Provider templates
                    <span class="normal-case tracking-normal text-zinc-600/80">— always available; add as many instances as you need</span>
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($presets as $template)
                        <button type="button"
                                data-template="{{ \Illuminate\Support\Str::slug($template['name']) }}"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-zinc-700/80 px-2.5 py-1.5 text-xs text-zinc-500 transition hover:border-violet-500/50 hover:text-zinc-200"
                                title="Add a {{ $template['name'] }} provider ({{ $template['base_url'] }})"
                                x-on:click="keyVisible = false; modal = @js(['id' => null, 'name' => $template['name'], 'base_url' => $template['base_url'], 'has_key' => false, 'from_template' => true])">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                            {{ $template['name'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Add / configure provider modal --}}
            <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" x-on:click="modal = null" aria-hidden="true"></div>
                <div class="relative w-full max-w-lg rounded-2xl border border-zinc-800 bg-zinc-900 p-5 shadow-2xl" role="dialog" aria-modal="true">
                    <h3 class="font-semibold text-white"
                        x-text="modal?.id
                            ? ('Configure “' + (modal?.name ?? '') + '”')
                            : (modal?.from_template ? ('Add “' + (modal?.name ?? '') + '” provider') : 'Add custom provider')"></h3>
                    <p class="mt-1 text-xs text-zinc-500">Any OpenAI-compatible endpoint — we append /chat/completions and /models.</p>
                    <p x-show="modal?.from_template" x-cloak class="mt-1 text-xs text-violet-300/80">
                        This template stays available — add another instance any time. A number is appended if the name is taken.
                    </p>

                    <form method="POST" class="mt-4 space-y-4"
                          x-bind:action="modal?.id ? providersUrl + '/' + modal.id : storeUrl">
                        @csrf
                        <input type="hidden" name="_method" x-bind:value="modal?.id ? 'PUT' : 'POST'">

                        <div>
                            <label for="provider-modal-name" class="label">Name <span class="text-violet-400" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                            <input type="text" id="provider-modal-name" name="name" required maxlength="120"
                                   placeholder="e.g. OpenRouter" class="input"
                                   x-bind:value="modal?.name ?? ''">
                        </div>

                        <div>
                            <label for="provider-modal-base-url" class="label">Base URL <span class="text-violet-400" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                            <input type="url" id="provider-modal-base-url" name="base_url" required
                                   placeholder="https://openrouter.ai/api/v1" class="input font-mono text-[13px]"
                                   x-bind:value="modal?.base_url ?? ''">
                        </div>

                        <div>
                            <label for="provider-modal-api-key" class="label">API Key
                                <span x-show="modal?.has_key" class="text-emerald-400">(saved — leave blank to keep it)</span>
                                <span x-show="!modal?.has_key" class="text-zinc-600">(optional)</span>
                            </label>
                            <input type="password" id="provider-modal-api-key" name="api_key"
                                   autocomplete="new-password"
                                   x-bind:type="keyVisible ? 'text' : 'password'"
                                   placeholder="sk-… or leave empty" class="input pr-10 font-mono text-[13px]">
                            <div class="mt-1 flex items-center gap-2 text-xs text-zinc-600">
                                <button type="button" class="underline underline-offset-2 hover:text-zinc-400"
                                        x-on:click="keyVisible = !keyVisible" x-text="keyVisible ? 'Hide key' : 'Show key'"></button>
                                <span x-text="modal?.has_key ? 'A key is stored for this provider.' : 'Optional for keyless endpoints (e.g. local Ollama).'"></span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 border-t border-zinc-800/80 pt-4">
                            <button type="submit" class="btn-primary" x-text="modal?.id ? 'Save changes' : 'Add provider'"></button>
                            <button type="button" class="btn-ghost" x-on:click="modal = null">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Delete provider confirm --}}
            <div x-show="confirmingDelete" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" x-on:click="confirmingDelete = null" aria-hidden="true"></div>
                <div class="relative w-full max-w-sm rounded-2xl border border-zinc-800 bg-zinc-900 p-5 shadow-2xl" role="dialog" aria-modal="true">
                    <h3 class="font-semibold text-white">Delete “<span x-text="confirmingDelete?.name"></span>”?</h3>
                    <p class="mt-2 text-sm text-zinc-400"
                       x-text="confirmingDelete?.agents_count > 0
                           ? ('Its ' + confirmingDelete.agents_count + ' agent(s) will keep working with their own copy of the endpoint settings.')
                           : 'No agents use this provider.'"></p>
                    <form method="POST" class="mt-4 flex justify-end gap-2"
                          x-bind:action="providersUrl + '/' + (confirmingDelete?.id ?? '')">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" x-on:click="confirmingDelete = null">Cancel</button>
                        <button type="submit" class="btn-danger !px-3 !py-1.5 text-xs">Delete provider</button>
                    </form>
                </div>
            </div>
        </section>

        {{-- ===================== New agent (row style) ===================== --}}
        <section class="card p-5" x-show="newOpen" @if (! $formOpen) x-cloak @endif>
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-zinc-400">New agent</h2>
            @include('agents._form', ['providers' => $providers])
        </section>

        {{-- ===================== Agents list ===================== --}}
        <div class="mt-8">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-zinc-400">
                Your agents <span class="normal-case tracking-normal text-zinc-600">({{ $agents->count() }})</span>
            </h2>

            <div class="space-y-3">
                @forelse ($agents as $agent)
                    @php($isEditing = $editId === $agent->id && ! $agent->trashed())

                    <div id="agent-{{ $agent->id }}" class="card group scroll-mt-20 p-4 transition hover:border-zinc-700 hover:bg-zinc-900/70"
                         x-data="{
                             testing: false,
                             result: null,
                             confirming: false,
                         }">

                        @if ($isEditing)
                            @include('agents._form', [
                                'agent' => $agent,
                                'isEdit' => true,
                                'providers' => $providers,
                            ])
                        @else
                            <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center">
                                <div class="flex min-w-0 flex-1 items-center gap-x-3 gap-y-1.5">
                                    <span class="h-3.5 w-3.5 shrink-0 rounded-full ring-2 ring-white/10" style="background: {{ $agent->color }}"></span>
                                    <span class="min-w-0 shrink truncate font-semibold" title="{{ $agent->name }}">{{ $agent->name }}</span>
                                    @if ($agent->provider)
                                        <span class="hidden max-w-[180px] items-center gap-1 rounded-md border border-violet-500/25 bg-violet-500/10 px-1.5 py-0.5 text-[11px] text-violet-300 md:inline-flex"
                                              title="Endpoint & key from {{ $agent->provider->name }}">
                                            <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="14" x="3" y="4" rx="2"/><path d="M8 20v-1M16 20v-1M3 10h18"/></svg>
                                            <span class="truncate">{{ $agent->provider->name }}</span>
                                        </span>
                                    @endif
                                    <code class="min-w-0 truncate rounded-md border border-zinc-700/70 bg-zinc-800/60 px-2 py-0.5 font-mono text-xs text-zinc-400" title="{{ $agent->model }}">{{ $agent->model }}</code>
                                    @if ($agent->trashed())
                                        <span class="shrink-0 rounded-md border border-zinc-700 px-2 py-0.5 text-xs text-zinc-500">deleted</span>
                                    @endif
                                </div>

                                <div class="flex shrink-0 items-center gap-2 self-start sm:self-auto">
                                    <button type="button" class="btn-ghost !px-3 !py-1.5" :disabled="testing" x-on:click="
                                        if (testing) return;
                                        testing = true; result = null;
                                        fetch('{{ route('agents.test', $agent) }}', {
                                            method: 'POST',
                                            headers: {
                                                Accept: 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                            },
                                        })
                                            .then((r) => r.json())
                                            .then((d) => result = d)
                                            .catch(() => result = { ok: false, error: 'Request failed.' })
                                            .finally(() => testing = false)">
                                        <svg x-show="!testing" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                                        <svg x-show="testing" x-cloak class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-9-9"/></svg>
                                        <span x-text="testing ? 'Testing…' : 'Test'"></span>
                                    </button>

                                    @unless ($agent->trashed())
                                        <a href="{{ route('agents.index', ['edit' => $agent->id]) }}#agent-{{ $agent->id }}"
                                           class="btn-ghost !px-3 !py-1.5">Edit</a>
                                        <button type="button" class="btn-danger !px-3 !py-1.5"
                                                x-on:click="confirming = true"
                                                x-on:keydown.escape.window="confirming = false">Delete</button>
                                    @endunless
                                </div>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 pl-[22px] text-xs text-zinc-500">
                                <span class="max-w-full truncate font-mono" title="{{ $agent->resolveBaseUrl() }}">{{ $agent->resolveBaseUrl() }}</span>
                                @if ($agent->temperature !== null)
                                    <span>temp {{ $agent->temperature }}</span>
                                @endif
                                @if ($agent->max_tokens !== null)
                                    <span>max {{ number_format($agent->max_tokens) }} tok</span>
                                @endif
                                @if ($agent->price_per_1m_input !== null && $agent->price_per_1m_output !== null)
                                    <span>${{ rtrim(rtrim(number_format($agent->price_per_1m_input, 6, '.', ''), '0'), '.') }} / ${{ rtrim(rtrim(number_format($agent->price_per_1m_output, 6, '.', ''), '0'), '.') }} per 1M</span>
                                @endif
                                <span>{{ $agent->timeout_seconds }}s timeout</span>
                            </div>

                            <div x-show="result" x-cloak class="mt-3 rounded-lg border px-3 py-2 text-xs"
                                 :class="result?.ok
                                    ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
                                    : 'border-red-500/30 bg-red-500/10 text-red-300'">
                                <template x-if="result?.ok">
                                    <span class="flex items-center gap-2">
                                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/></svg>
                                        OK — responded in <span x-text="result.latency_ms"></span>ms (<span x-text="result.model"></span>)
                                    </span>
                                </template>
                                <template x-if="result && !result.ok">
                                    <span class="break-words" x-text="result.error"></span>
                                </template>
                            </div>

                            @unless ($agent->trashed())
                                <div x-show="confirming" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                                    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" x-on:click="confirming = false" aria-hidden="true"></div>
                                    <div class="relative w-full max-w-sm rounded-2xl border border-zinc-800 bg-zinc-900 p-5 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="delete-agent-{{ $agent->id }}">
                                        <h3 id="delete-agent-{{ $agent->id }}" class="font-semibold text-white">Delete “{{ $agent->name }}”?</h3>
                                        <p class="mt-2 text-sm text-zinc-400">It will be soft-deleted and stays visible in existing chats. You can't add it to new chats.</p>
                                        <form method="POST" action="{{ route('agents.destroy', $agent) }}" class="mt-4 flex justify-end gap-2">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" x-on:click="confirming = false">Cancel</button>
                                            <button type="submit" class="btn-danger !px-3 !py-1.5 text-xs">Delete agent</button>
                                        </form>
                                    </div>
                                </div>
                            @endunless
                        @endif
                    </div>
                @empty
                    <div class="card flex flex-col items-center justify-center gap-1 p-12 text-center">
                        <svg class="mb-2 h-10 w-10 text-zinc-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2M20 14h2M15 13v2M9 13v2"/></svg>
                        <p class="text-sm font-medium text-zinc-300">No agents yet</p>
                        <p class="text-sm text-zinc-500">Hit “New agent” above — pick any model from your provider groups.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
