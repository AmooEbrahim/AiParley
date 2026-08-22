<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'chat_id',
        'chat_agent_id',
        'agent_id',
        'type',
        'target_chat_agent_id',
        'content',
        'prompt_tokens',
        'completion_tokens',
        'cost',
        'latency_ms',
        'model',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'cost' => 'float',
            'latency_ms' => 'integer',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function chatAgent(): BelongsTo
    {
        return $this->belongsTo(ChatAgent::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class)->withTrashed();
    }

    public function targetChatAgent(): BelongsTo
    {
        return $this->belongsTo(ChatAgent::class);
    }

    public function toDisplayArray(): array
    {
        return [
            'id' => $this->id,
            'agent_name' => $this->chatAgent?->displayName() ?? ($this->agent?->name ?? 'Deleted agent'),
            'agent_color' => ($this->chatAgent?->color ?: null) ?? ($this->agent?->color ?? '#a78bfa'),
            'type' => $this->type,
            'note_target' => $this->type === 'note'
                ? ($this->targetChatAgent?->displayName() ?? 'all')
                : null,
            'content' => $this->content,
            'model' => $this->model,
            'prompt_tokens' => $this->prompt_tokens,
            'completion_tokens' => $this->completion_tokens,
            'cost' => $this->cost,
            'latency_ms' => $this->latency_ms,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
