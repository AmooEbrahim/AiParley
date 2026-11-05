<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    /** Known OpenAI-compatible endpoints, seeded idempotently by migration. */
    public const PRESETS = [
        ['name' => 'OpenAI', 'base_url' => 'https://api.openai.com/v1'],
        ['name' => 'OpenRouter', 'base_url' => 'https://openrouter.ai/api/v1'],
        ['name' => 'Anthropic', 'base_url' => 'https://api.anthropic.com/v1'],
        ['name' => 'Google Gemini', 'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai'],
        ['name' => 'Groq', 'base_url' => 'https://api.groq.com/openai/v1'],
        ['name' => 'DeepSeek', 'base_url' => 'https://api.deepseek.com/v1'],
        ['name' => 'Mistral', 'base_url' => 'https://api.mistral.ai/v1'],
        ['name' => 'xAI (Grok)', 'base_url' => 'https://api.x.ai/v1'],
        ['name' => 'Together AI', 'base_url' => 'https://api.together.xyz/v1'],
        ['name' => 'Fireworks AI', 'base_url' => 'https://api.fireworks.ai/inference/v1'],
        ['name' => 'Cerebras', 'base_url' => 'https://api.cerebras.ai/v1'],
        ['name' => 'Perplexity', 'base_url' => 'https://api.perplexity.ai'],
        ['name' => 'OpenCode Zen', 'base_url' => 'https://opencode.ai/zen/v1'],
        ['name' => 'OpenCode Go', 'base_url' => 'https://opencode.ai/go/v1'],
        ['name' => 'Ollama (local)', 'base_url' => 'http://127.0.0.1:11434/v1'],
        ['name' => 'LM Studio (local)', 'base_url' => 'http://127.0.0.1:1234/v1'],
        ['name' => 'vLLM (local)', 'base_url' => 'http://127.0.0.1:8000/v1'],
    ];

    protected $fillable = [
        'name',
        'base_url',
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'models_cache' => 'array',
            'models_cached_at' => 'datetime',
        ];
    }

    public function agents()
    {
        return $this->hasMany(Agent::class);
    }

    /** A provider is "active" once the user configured or used it. */
    public function isActive(): bool
    {
        return (string) $this->api_key !== ''
            || $this->agents_count > 0
            || $this->models_cached_at !== null;
    }

    /** @return array<int, array{id: string, name: string, context_length: ?int, price_in_1m: ?float, price_out_1m: ?float}> */
    public function cachedModels(): array
    {
        return is_array($this->models_cache) ? $this->models_cache : [];
    }
}
