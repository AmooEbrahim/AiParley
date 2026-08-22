@extends('layouts.app')

@section('title', 'Global Prompt · AiParley')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">Global Prompt</h1>
        <p class="mt-1 text-sm text-zinc-500">Sent to every agent, before its own prompts. Applies from the next generated message.</p>
    </div>

    <div class="card max-w-3xl p-5">
        <div class="mb-5 flex flex-wrap items-center gap-1.5 rounded-xl border border-zinc-800 bg-zinc-900/60 px-4 py-3 text-xs text-zinc-400">
            <span class="rounded-md bg-violet-500/15 px-2 py-1 font-semibold text-violet-300">global prompt</span>
            <span class="text-zinc-600">&rarr;</span>
            <span class="rounded-md bg-zinc-800 px-2 py-1">agent default prompt</span>
            <span class="text-zinc-600">&rarr;</span>
            <span class="rounded-md bg-zinc-800 px-2 py-1">chat-specific prompt</span>
            <span class="text-zinc-600">&rarr;</span>
            <span class="rounded-md bg-zinc-800 px-2 py-1">messages</span>
            <span class="ml-auto text-zinc-600">empty parts are skipped</span>
        </div>

        <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="label">Global system prompt</label>
                <textarea name="global_prompt" rows="8"
                          class="input font-mono text-[13px] leading-relaxed"
                          placeholder="e.g. You are part of a round-table debate. Keep answers under 150 words and address other speakers by name.">{{ old('global_prompt', $globalPrompt) }}</textarea>
            </div>

            <button type="submit" class="btn-primary">Save</button>
        </form>
    </div>
@endsection
