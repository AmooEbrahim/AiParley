<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AiParley')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased selection:bg-violet-500/30">
    <nav class="border-b border-zinc-800/80 bg-zinc-950/80 backdrop-blur sticky top-0 z-40">
        <div class="mx-auto flex max-w-6xl items-center gap-6 px-4 py-3.5">
            <a href="{{ route('chats.index') }}" class="flex items-center gap-2 text-lg font-semibold tracking-tight">
                <span aria-hidden="true">&#128419;</span> AiParley
            </a>
            <div class="flex items-center gap-1 text-sm font-medium">
                <a href="{{ route('chats.index') }}"
                   class="rounded-md px-3 py-1.5 {{ request()->routeIs('chats.*') && ! request()->routeIs('chats.create') ? 'bg-zinc-800 text-white' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }} transition">Chats</a>
                <a href="{{ route('agents.index') }}"
                   class="rounded-md px-3 py-1.5 {{ request()->routeIs('agents.*') ? 'bg-zinc-800 text-white' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }} transition">Agents</a>
                <a href="{{ route('settings.edit') }}"
                   class="rounded-md px-3 py-1.5 {{ request()->routeIs('settings.*') ? 'bg-zinc-800 text-white' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }} transition">Global Prompt</a>
            </div>
            <span class="ml-auto hidden text-xs text-zinc-600 sm:block">local multi-agent group chat</span>
        </div>
    </nav>

    <main class="mx-auto max-w-6xl px-4 py-8">
        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-transition.opacity x-init="setTimeout(() => show = false, 4000)"
                 class="mb-6 flex items-center justify-between gap-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2.5 text-sm text-emerald-300">
                <span>{{ session('success') }}</span>
                <button x-on:click="show = false" class="text-emerald-400/70 hover:text-emerald-200">&times;</button>
            </div>
        @endif

        @if (count($errors) > 0)
            <div class="mb-6 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm text-red-300">
                <p class="font-medium mb-1">Please fix the following:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
