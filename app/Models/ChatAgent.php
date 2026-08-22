<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatAgent extends Model
{
    protected $fillable = [
        'chat_id',
        'agent_id',
        'color',
        'display_name',
        'position',
        'initial_prompt',
        'active',
        'joined_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'active' => 'boolean',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class)->withTrashed();
    }

    public function displayName(): string
    {
        if (is_string($this->display_name) && trim($this->display_name) !== '') {
            return $this->display_name;
        }

        return $this->agent?->name ?? 'Deleted agent';
    }
}
