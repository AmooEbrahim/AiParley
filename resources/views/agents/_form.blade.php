@props(['agent' => null, 'isEdit' => false, 'providers' => [], 'cancelAction' => null])

@php($uid = $isEdit ? 'edit-'.$agent->id : 'new')
@php($resolvedBaseUrl = $agent?->base_url ?? $agent?->provider?->base_url ?? 'https://api.openai.com/v1')
@php($advancedOpen = request()->old() !== [])

<form method="POST"
      action="{{ $isEdit ? route('agents.update', $agent) : route('agents.store') }}"
      class="space-y-4"
      x-data="agentForm({
          providers: @js($providers),
          providerId: @js((string) (old('provider_id', $agent?->provider_id) ?? '')),
          customBaseUrl: @js(old('base_url', $resolvedBaseUrl)),
          customApiKey: @js(old('api_key', $agent?->api_key)),
          model: @js(old('model', $agent?->model ?? '')),
          advancedOpen: @js($advancedOpen),
      })">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <input type="hidden" name="provider_id" x-bind:value="isCustom ? '' : providerId">

    {{-- Row 1: identity + endpoint/model --}}
    <div class="grid grid-cols-12 items-start gap-3">
        <div class="col-span-12 md:col-span-4">
            <label for="{{ $uid }}-name" class="label">Name <span class="text-violet-400" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
            <input type="text" id="{{ $uid }}-name" name="name" value="{{ old('name', $agent?->name) }}" required
                   placeholder="e.g. Socrates" maxlength="120" class="input">
        </div>

        <div class="col-span-12 sm:col-span-6 md:col-span-2" x-data="{ hex: @js(strtoupper(old('color', $agent?->color ?? '#a78bfa'))) }">
            <label for="{{ $uid }}-color-hex" class="label">Color <span class="text-violet-400" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
            <div class="flex items-center gap-2">
                <input type="color" aria-label="Pick a color" x-model="hex"
                       class="h-[38px] w-10 shrink-0 cursor-pointer rounded-md border border-zinc-700 bg-zinc-900 p-1">
                <input type="text" id="{{ $uid }}-color-hex" name="color" maxlength="7"
                       pattern="#[0-9a-fA-F]{6}" required placeholder="#A78BFA" spellcheck="false"
                       x-model="hex" class="input w-full min-w-0 font-mono text-[13px] uppercase">
            </div>
        </div>

        <div class="col-span-12 md:col-span-6"
             x-on:click.outside="open = false"
             x-on:keydown.escape="open = false">
            <label for="{{ $uid }}-model" class="label">
                Model <span class="text-violet-400" aria-hidden="true">*</span><span class="sr-only">(required)</span>
                <span class="ml-1 font-normal normal-case tracking-normal text-zinc-600">— grouped by provider, or type any model id</span>
            </label>

            <div class="relative">
                <input type="text" id="{{ $uid }}-model" name="model" required autocomplete="off" spellcheck="false"
                       placeholder="Search models across providers…" class="input pr-9 font-mono text-[13px]"
                       x-model="model"
                       x-on:click="open = true; highlighted = -1"
                       x-on:focus="open = true"
                       x-on:input="open = true; highlighted = -1"
                       x-on:keydown="onPickerKeydown($event)">
                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500 transition-transform"
                     :class="open ? 'rotate-180' : ''"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>

                {{-- Grouped dropdown --}}
                <div x-show="open" x-cloak
                     class="absolute inset-x-0 top-full z-40 mt-1 overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900 shadow-2xl shadow-black/60">
                    <div class="max-h-72 divide-y divide-zinc-800/70 overflow-y-auto">
                        <template x-for="v in viewGroups" :key="v.g.id">
                            <div>
                                <button type="button"
                                        class="sticky top-0 z-10 flex w-full items-center gap-2 border-b border-zinc-800/70 bg-zinc-900/95 px-3 py-2 text-left backdrop-blur transition hover:bg-zinc-800/80"
                                        x-on:click="toggleGroup(v.g)">
                                    <svg class="h-3.5 w-3.5 shrink-0 text-zinc-500 transition-transform"
                                         :class="v.showModels ? 'rotate-90' : ''"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                    <span class="min-w-0 truncate text-xs font-semibold"
                                          :class="v.nameHit || v.showModels ? 'text-zinc-200' : 'text-zinc-400'"
                                          x-text="v.g.name"></span>
                                    <span class="shrink-0 text-[11px] text-zinc-500" x-text="v.g.count + (v.g.count === 1 ? ' model' : ' models')"></span>
                                    <span x-show="v.g.isActive" class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400/80" title="Configured / in use"></span>
                                    <span class="ml-auto shrink-0 rounded p-0.5 text-zinc-500 transition hover:bg-zinc-700 hover:text-zinc-200"
                                          role="button" tabindex="-1" title="Fetch model list from this provider"
                                          x-on:click.stop="loadGroup(v.g, true)">
                                        <svg x-show="!v.g.loading" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                                        <svg x-show="v.g.loading" x-cloak class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-9-9"/></svg>
                                    </span>
                                </button>

                                <template x-for="m in v.models" :key="m.id">
                                    <button type="button"
                                            class="flex w-full items-center justify-between gap-3 px-3 py-1.5 pl-7 text-left transition"
                                            :class="isHighlighted(v.g, m) ? 'bg-violet-500/15' : 'hover:bg-zinc-800/70'"
                                            x-on:mouseenter="highlighted = flatItems().findIndex((it) => it.type === 'model' && it.m === m)"
                                            x-on:click="choose({ type: 'model', g: v.g, m })">
                                        <span class="min-w-0 truncate font-mono text-[13px] text-zinc-100" x-text="m.id"></span>
                                        <span class="shrink-0 whitespace-nowrap text-[11px] text-zinc-500" x-text="metaLine(m)"></span>
                                    </button>
                                </template>

                                <template x-if="v.showModels && v.models.length === 0 && v.g.loading">
                                    <div class="px-3 py-1.5 pl-7 text-[11px] text-zinc-500">Fetching models…</div>
                                </template>
                                <template x-if="v.showModels && v.models.length === 0 && !v.g.loading && !v.g.error">
                                    <div class="px-3 py-1.5 pl-7 text-[11px] text-zinc-500">No models cached yet — use ⟳ on this group.</div>
                                </template>
                                <template x-if="v.g.error">
                                    <div class="px-3 py-1.5 pl-7 text-[11px] text-amber-400/80" x-text="'Could not load models — ' + v.g.error"></div>
                                </template>
                            </div>
                        </template>
                    </div>
                    <button type="button"
                            class="flex w-full items-center gap-2 border-t border-zinc-700 bg-zinc-900 px-3 py-2.5 text-left text-xs font-medium text-zinc-300 transition hover:bg-zinc-800/70"
                            :class="isCustomHighlighted() ? 'bg-violet-500/15' : ''"
                            x-on:mouseenter="highlighted = flatItems().length - 1"
                            x-on:click="switchToCustom()">
                        <svg class="h-3.5 w-3.5 shrink-0 text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                        Use custom endpoint…
                    </button>
                </div>
            </div>

            <div class="mt-1.5 flex min-h-[22px] items-center gap-2 text-xs">
                <template x-if="!isCustom && selectedGroup">
                    <span class="inline-flex max-w-full items-center gap-1.5 rounded-md border border-violet-500/25 bg-violet-500/10 px-2 py-0.5 text-violet-300">
                        <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="14" x="3" y="4" rx="2"/><path d="M8 20v-1M16 20v-1M3 10h18"/></svg>
                        <span class="truncate">via <strong x-text="selectedGroup.name"></strong></span>
                        <button type="button" class="shrink-0 text-violet-400/70 hover:text-violet-200"
                                title="Switch to a custom endpoint instead" aria-label="Clear provider"
                                x-on:click="switchToCustom()">&times;</button>
                    </span>
                </template>
                <template x-if="isCustom">
                    <span class="text-zinc-600">Custom mode — endpoint & key below, model free-typed above.</span>
                </template>
            </div>
        </div>
    </div>

    {{-- Row 2: manual endpoint, only without a provider --}}
    <div x-show="isCustom" x-cloak class="grid grid-cols-12 gap-3">
        <div class="col-span-12 md:col-span-7">
            <label for="{{ $uid }}-base-url" class="label">Base URL <span class="text-violet-400" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
            <input type="url" id="{{ $uid }}-base-url" name="base_url"
                   x-bind:required="isCustom"
                   x-model="customBaseUrl"
                   placeholder="https://api.openai.com/v1" class="input font-mono text-[13px]">
        </div>
        <div class="col-span-12 md:col-span-5">
            <label for="{{ $uid }}-api-key" class="label">API Key <span class="text-violet-400" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
            <input type="password" id="{{ $uid }}-api-key" name="api_key"
                   x-bind:required="isCustom"
                   x-model="customApiKey"
                   autocomplete="off" placeholder="sk-…" class="input font-mono text-[13px]">
        </div>
    </div>

    {{-- Advanced options --}}
    <div class="rounded-xl border border-zinc-800/70 bg-zinc-900/40">
        <button type="button"
                class="flex w-full items-center gap-2 px-3 py-2.5 text-xs font-medium text-zinc-300 transition hover:text-white"
                x-on:click="advancedOpen = !advancedOpen">
            <svg class="h-3 w-3 text-zinc-500 transition-transform" :class="advancedOpen ? 'rotate-90' : ''"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            Advanced options
            <span class="font-normal text-zinc-600">(system prompt · temperature · limits · pricing · timeout)</span>
        </button>
        <div x-show="advancedOpen" x-cloak class="space-y-3 border-t border-zinc-800/70 p-3">
            <div>
                <label for="{{ $uid }}-prompt" class="label">Default system prompt <span class="text-zinc-600">(fallback when a chat sets none)</span></label>
                <textarea id="{{ $uid }}-prompt" name="default_system_prompt" rows="2"
                          placeholder="e.g. You are a sharp, concise debate partner."
                          class="input resize-y font-mono text-[13px]">{{ old('default_system_prompt', $agent?->default_system_prompt) }}</textarea>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <div>
                    <label for="{{ $uid }}-temp" class="label" title="Sampling randomness (0 = deterministic, 2 = wild)">Temp <span class="text-zinc-600">(opt.)</span></label>
                    <input type="number" id="{{ $uid }}-temp" name="temperature" step="0.1" min="0" max="2"
                           value="{{ old('temperature', $agent?->temperature) }}" placeholder="1.0" class="input">
                </div>
                <div>
                    <label for="{{ $uid }}-max-tokens" class="label">Max tokens <span class="text-zinc-600">(opt.)</span></label>
                    <input type="number" id="{{ $uid }}-max-tokens" name="max_tokens" min="1" max="100000"
                           value="{{ old('max_tokens', $agent?->max_tokens) }}" placeholder="auto" class="input">
                </div>
                <div>
                    <label for="{{ $uid }}-price-in" class="label">$ / 1M in</label>
                    <input type="number" id="{{ $uid }}-price-in" name="price_per_1m_input" step="0.000001" min="0"
                           value="{{ old('price_per_1m_input', $agent?->price_per_1m_input) }}" placeholder="0.00" class="input">
                </div>
                <div>
                    <label for="{{ $uid }}-price-out" class="label">$ / 1M out</label>
                    <input type="number" id="{{ $uid }}-price-out" name="price_per_1m_output" step="0.000001" min="0"
                           value="{{ old('price_per_1m_output', $agent?->price_per_1m_output) }}" placeholder="0.00" class="input">
                </div>
                <div class="col-span-2 sm:col-span-3 lg:col-span-1">
                    <label for="{{ $uid }}-timeout" class="label">Timeout (s) <span class="text-violet-400" aria-hidden="true">*</span></label>
                    <input type="number" id="{{ $uid }}-timeout" name="timeout_seconds" min="5" max="300" required
                           value="{{ old('timeout_seconds', $agent?->timeout_seconds ?? 120) }}" class="input">
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-2 border-t border-zinc-800/80 pt-4">
        <button type="submit" class="btn-primary">{{ $isEdit ? 'Save changes' : 'Create agent' }}</button>
        @if ($cancelAction)
            <button type="button" class="btn-ghost" x-on:click="{{ $cancelAction }}">Cancel</button>
        @else
            <a href="{{ route('agents.index') }}" class="btn-ghost">Cancel</a>
        @endif
    </div>
</form>
