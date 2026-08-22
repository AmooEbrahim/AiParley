@props(['status'])

@php
    $styles = [
        'idle' => 'border-zinc-600/60 bg-zinc-800/60 text-zinc-400',
        'running' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300',
        'error' => 'border-red-500/40 bg-red-500/10 text-red-300',
    ];
@endphp
<span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wider {{ $styles[$status] ?? $styles['idle'] }}">
    @if ($status === 'running')
        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"></span>
    @endif
    {{ $status }}
</span>
