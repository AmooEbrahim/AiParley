@extends('layouts.app')

@section('title', 'Chats · AiParley')

@section('content')
    <div class="mb-6 flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Chats</h1>
            <p class="mt-1 text-sm text-zinc-500">Group conversations between your agents.</p>
        </div>
        <a href="{{ route('chats.create') }}" class="btn-primary">+ New chat</a>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($chats as $chat)
            <a href="{{ route('chats.show', $chat) }}"
               class="card group p-5 transition hover:border-violet-500/40 hover:bg-zinc-900/80">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-semibold group-hover:text-violet-300">{{ $chat->title }}</h2>
                    @include('chats._status_pill', ['status' => $chat->status])
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($chat->chatAgents->where('active', true)->sortBy('position') as $chatAgent)
                        @php($chipColor = $chatAgent->color ?: ($chatAgent->agent->color ?? '#555'))
                        <span class="flex items-center gap-1.5 rounded-full border border-zinc-700/70 bg-zinc-800/50 py-0.5 pl-1.5 pr-2.5 text-xs text-zinc-300">
                            <span class="h-2 w-2 rounded-full" style="background: {{ $chipColor }}"></span>
                            {{ $chatAgent->display_name ?: ($chatAgent->agent->name ?? 'deleted agent') }}
                        </span>
                    @endforeach
                </div>
                <div class="mt-4 flex gap-4 text-xs text-zinc-500">
                    <span>{{ $chat->messages_count }} messages</span>
                    <span>budget {{ $chat->auto_budget }}</span>
                </div>
            </a>
        @empty
            <div class="card col-span-full p-12 text-center">
                <p class="text-sm text-zinc-400">No chats yet.</p>
                <a href="{{ route('chats.create') }}" class="btn-primary mt-4 inline-flex">Create your first chat</a>
            </div>
        @endforelse
    </div>
@endsection
