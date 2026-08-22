<?php

namespace App\Services;

use App\Models\ChatAgent;
use App\Models\Setting;

class ContextBuilder
{
    /**
     * Build the OpenAI-style messages array from the speaker's perspective.
     * Never store roles — always derived at call time.
     */
    public function build(ChatAgent $speaker): array
    {
        $messages = [];

        $system = $this->systemPrompt($speaker);
        if ($system !== '') {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        foreach ($speaker->chat->messages()->with('chatAgent.agent')->orderBy('id')->get() as $message) {
            // Director notes: only the targeted participant (or everyone when untargeted) sees them.
            if ($message->type === 'note') {
                if ($message->target_chat_agent_id !== null && $message->target_chat_agent_id !== $speaker->id) {
                    continue;
                }

                $messages[] = ['role' => 'user', 'content' => "[Director]: {$message->content}"];

                continue;
            }

            if ($message->chat_agent_id !== null && $message->chat_agent_id === $speaker->id) {
                $messages[] = ['role' => 'assistant', 'content' => $message->content];
            } else {
                $name = $message->chatAgent?->displayName() ?? ($message->agent?->name ?? 'Unknown');
                $messages[] = ['role' => 'user', 'content' => "[{$name}]: {$message->content}"];
            }
        }

        // Some OpenAI-compatible providers return an empty response when the
        // request does not end with a user message (e.g. first turn of a fresh
        // chat sends only the system prompt). Always guarantee one.
        $lastRole = $messages !== [] ? $messages[count($messages) - 1]['role'] : null;

        if ($lastRole === null) {
            $messages[] = ['role' => 'user', 'content' => 'Begin the conversation.'];
        } elseif ($lastRole !== 'user') {
            $messages[] = ['role' => 'user', 'content' => '(Continue the conversation.)'];
        }

        return $messages;
    }

    /**
     * global_prompt → agent default_system_prompt → chat_agent initial_prompt.
     * Empty parts are skipped; non-empty parts joined with a blank line.
     */
    public function systemPrompt(ChatAgent $speaker): string
    {
        $parts = array_filter([
            Setting::get('global_prompt'),
            $speaker->agent->default_system_prompt,
            $speaker->initial_prompt,
        ], fn ($p) => is_string($p) && trim($p) !== '');

        return implode("\n\n", $parts);
    }
}
