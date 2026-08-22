@extends('layouts.app')

@section('title', 'Agents · AiParley')

@section('content')
    <div class="mb-6 flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Agents</h1>
            <p class="mt-1 text-sm text-zinc-500">Each agent is an OpenAI-compatible endpoint with its own model, key and persona.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,420px)_1fr] items-start">
        <div class="card p-5">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-zinc-400">New agent</h2>
            @include('agents._form')
        </div>

        <div class="space-y-3">
            @forelse ($agents as $agent)
                <div class="card p-4" x-data="{
                    testing: false,
                    result: null,
                    test() {
                        this.testing = true; this.result = null;
                        fetch('{{ route('agents.test', $agent) }}', {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                        })
                            .then(r => r.json())
                            .then(d => this.result = d)
                            .catch(() => this.result = { ok: false, error: 'Request failed.' })
                            .finally(() => this.testing = false);
                    }
                }">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <span class="h-3.5 w-3.5 shrink-0 rounded-full" style="background: {{ $agent->color }}"></span>
                        <span class="font-semibold">{{ $agent->name }}</span>
                        <span class="rounded-md border border-zinc-700/70 bg-zinc-800/60 px-2 py-0.5 font-mono text-xs text-zinc-400">{{ $agent->model }}</span>
                        @if ($agent->trashed())
                            <span class="rounded-md border border-zinc-700 px-2 py-0.5 text-xs text-zinc-500">deleted</span>
                        @endif
                        <span class="ml-auto flex items-center gap-2">
                            <button type="button" class="btn-ghost !px-3 !py-1.5" :disabled="testing"
                                    x-on:click="test()" x-text="testing ? 'Testing…' : 'Test'"></button>
                            <a href="{{ route('agents.edit', $agent) }}" class="btn-ghost !px-3 !py-1.5">Edit</a>
                            @unless ($agent->trashed())
                                <form method="POST" action="{{ route('agents.destroy', $agent) }}"
                                      onsubmit="return confirm('Soft-delete this agent? It stays visible in existing chats.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-danger !px-3 !py-1.5">Delete</button>
                                </form>
                            @endunless
                        </span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-zinc-500">
                        <span class="font-mono">{{ $agent->base_url }}</span>
                        @if ($agent->temperature !== null)
                            <span>temp {{ $agent->temperature }}</span>
                        @endif
                        @if ($agent->max_tokens !== null)
                            <span>max {{ $agent->max_tokens }} tok</span>
                        @endif
                        @if ($agent->price_per_1m_input !== null && $agent->price_per_1m_output !== null)
                            <span>${{ $agent->price_per_1m_input }} / ${{ $agent->price_per_1m_output }} per 1M</span>
                        @endif
                        <span>timeout {{ $agent->timeout_seconds }}s</span>
                    </div>
                    <div x-show="result" x-cloak class="mt-3 rounded-lg border px-3 py-2 text-xs"
                         :class="result?.ok
                            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
                            : 'border-red-500/30 bg-red-500/10 text-red-300'"
                         x-text="result?.ok
                            ? `OK — responded in ${result.latency_ms}ms (${result.model})`
                            : result?.error">
                    </div>
                </div>
            @empty
                <div class="card p-10 text-center text-sm text-zinc-500">
                    No agents yet — create the first one on the left.
                </div>
            @endforelse
        </div>
    </div>
@endsection
