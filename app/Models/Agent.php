<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'color',
        'base_url',
        'api_key',
        'model',
        'temperature',
        'max_tokens',
        'default_system_prompt',
        'price_per_1m_input',
        'price_per_1m_output',
        'timeout_seconds',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'max_tokens' => 'integer',
            'price_per_1m_input' => 'float',
            'price_per_1m_output' => 'float',
            'timeout_seconds' => 'integer',
        ];
    }

    public function chatAgents()
    {
        return $this->hasMany(ChatAgent::class);
    }

    public function priceFor(int $promptTokens, int $completionTokens): ?float
    {
        if ($this->price_per_1m_input === null || $this->price_per_1m_output === null) {
            return null;
        }

        return ($promptTokens / 1_000_000) * $this->price_per_1m_input
            + ($completionTokens / 1_000_000) * $this->price_per_1m_output;
    }
}
