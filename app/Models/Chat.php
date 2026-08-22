<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    protected $fillable = ['title', 'status', 'auto_budget', 'message_limit', 'last_error', 'error_agent_id'];

    protected function casts(): array
    {
        return [
            'auto_budget' => 'integer',
            'message_limit' => 'integer',
        ];
    }

    public function chatAgents()
    {
        return $this->hasMany(ChatAgent::class);
    }

    public function activeChatAgents()
    {
        return $this->hasMany(ChatAgent::class)
            ->where('active', true)
            ->orderBy('position')
            ->orderBy('id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function messageCount(): int
    {
        return $this->messages()->count();
    }

    /**
     * Messages that occupy a round-robin slot (agent-generated or impersonated).
     * Director notes don't count toward the auto-run budget.
     */
    public function agentMessageCount(): int
    {
        return $this->messages()->whereNotNull('chat_agent_id')->count();
    }

    public function stats(): array
    {
        $agg = $this->messages()
            ->selectRaw('COUNT(*) as total_messages')
            ->selectRaw('COALESCE(SUM(prompt_tokens), 0) as prompt_tokens')
            ->selectRaw('COALESCE(SUM(completion_tokens), 0) as completion_tokens')
            ->selectRaw('COALESCE(SUM(cost), 0) as cost')
            ->selectRaw('COALESCE(AVG(latency_ms), 0) as avg_latency')
            ->first();

        return [
            'total_messages' => (int) $agg->total_messages,
            'prompt_tokens' => (int) $agg->prompt_tokens,
            'completion_tokens' => (int) $agg->completion_tokens,
            'total_tokens' => (int) $agg->prompt_tokens + (int) $agg->completion_tokens,
            'cost' => (float) $agg->cost,
            'avg_latency_ms' => (int) round((float) $agg->avg_latency),
        ];
    }
}
