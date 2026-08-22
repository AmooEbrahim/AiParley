@props(['agent' => null, 'isEdit' => false])

<form method="POST"
      action="{{ $isEdit ? route('agents.update', $agent) : route('agents.store') }}"
      class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label class="label">Name</label>
            <input type="text" name="name" value="{{ old('name', $agent?->name) }}" required
                   placeholder="e.g. Socrates" class="input">
        </div>
        <div class="grid grid-cols-[auto_1fr] gap-3">
            <div>
                <label class="label">Color</label>
                <input type="color" name="color" value="{{ old('color', $agent?->color ?? '#a78bfa') }}"
                       class="h-[38px] w-14 cursor-pointer rounded-md border border-zinc-700 bg-zinc-900 p-1">
            </div>
            <div>
                <label class="label">Model</label>
                <input type="text" name="model" value="{{ old('model', $agent?->model) }}" required
                       placeholder="e.g. gpt-4o-mini" class="input">
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label class="label">Base URL</label>
            <input type="url" name="base_url" value="{{ old('base_url', $agent?->base_url ?? 'https://api.openai.com/v1') }}"
                   required class="input">
        </div>
        <div>
            <label class="label">API Key</label>
            <input type="password" name="api_key" value="{{ old('api_key', $agent?->api_key) }}" required
                   autocomplete="off" class="input">
        </div>
    </div>

    <div>
        <label class="label">Default system prompt <span class="text-zinc-600">(fallback when a chat sets none)</span></label>
        <textarea name="default_system_prompt" rows="3" class="input font-mono text-[13px]">{{ old('default_system_prompt', $agent?->default_system_prompt) }}</textarea>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
        <div>
            <label class="label">Temp</label>
            <input type="number" name="temperature" step="0.1" min="0" max="2"
                   value="{{ old('temperature', $agent?->temperature) }}" class="input">
        </div>
        <div>
            <label class="label">Max tokens</label>
            <input type="number" name="max_tokens" min="1"
                   value="{{ old('max_tokens', $agent?->max_tokens) }}" class="input">
        </div>
        <div>
            <label class="label">$ / 1M in</label>
            <input type="number" name="price_per_1m_input" step="0.000001" min="0"
                   value="{{ old('price_per_1m_input', $agent?->price_per_1m_input) }}" class="input">
        </div>
        <div>
            <label class="label">$ / 1M out</label>
            <input type="number" name="price_per_1m_output" step="0.000001" min="0"
                   value="{{ old('price_per_1m_output', $agent?->price_per_1m_output) }}" class="input">
        </div>
        <div>
            <label class="label">Timeout (s)</label>
            <input type="number" name="timeout_seconds" min="5" max="300"
                   value="{{ old('timeout_seconds', $agent?->timeout_seconds ?? 120) }}" class="input">
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary">{{ $isEdit ? 'Save changes' : 'Create agent' }}</button>
        @if ($isEdit)
            <a href="{{ route('agents.index') }}" class="btn-ghost">Cancel</a>
        @endif
    </div>
</form>
