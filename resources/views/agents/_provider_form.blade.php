@props(['provider' => null, 'closeAction' => null, 'title' => null])

@php($isEdit = $provider !== null)

<form method="POST"
      action="{{ $isEdit ? route('providers.update', ['provider' => $provider['id']]) : route('providers.store') }}"
      class="space-y-4"
      x-data="{ keyVisible: false }">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="provider-name" class="label">Name <span class="text-violet-400" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
            <input type="text" id="provider-name" name="name" required maxlength="120"
                   value="{{ old('name', $provider['name'] ?? '') }}"
                   placeholder="e.g. OpenRouter" class="input">
        </div>
        <div class="sm:col-span-1">
            <label for="provider-base-url" class="label">Base URL <span class="text-violet-400" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
            <input type="url" id="provider-base-url" name="base_url" required
                   value="{{ old('base_url', $provider['base_url'] ?? '') }}"
                   placeholder="https://openrouter.ai/api/v1" class="input font-mono text-[13px]">
        </div>
    </div>

    <div>
        <label for="provider-api-key" class="label">API Key
            @if ($isEdit && ($provider['has_key'] ?? false))
                <span class="text-emerald-400">(saved — leave blank to keep it)</span>
            @else
                <span class="text-zinc-600">(optional for keyless endpoints)</span>
            @endif
        </label>
        <div class="relative">
            <input type="password" id="provider-api-key" name="api_key"
                   value="{{ old('api_key') }}"
                   x-bind:type="keyVisible ? 'text' : 'password'"
                   autocomplete="new-password" placeholder="{{ ($provider['has_key'] ?? false) ? '•••••••• (unchanged)' : 'sk-… or leave empty' }}"
                   class="input pr-10 font-mono text-[13px]">
            <button type="button" x-on:click="keyVisible = !keyVisible"
                    class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-zinc-500 transition hover:text-zinc-300"
                    aria-label="Toggle key visibility">
                <svg x-show="!keyVisible" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg x-show="keyVisible" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
            </button>
        </div>
    </div>

    <div class="flex items-center gap-2 border-t border-zinc-800/80 pt-4">
        <button type="submit" class="btn-primary">{{ $isEdit ? 'Save provider' : 'Add provider' }}</button>
        <button type="button" class="btn-ghost" x-on:click="{{ $closeAction }}">Cancel</button>
    </div>
</form>
