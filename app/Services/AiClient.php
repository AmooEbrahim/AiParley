<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AiClient
{
    /**
     * Call an OpenAI-compatible /chat/completions endpoint.
     *
     * @return array{content: string, prompt_tokens: ?int, completion_tokens: ?int, model: ?string}
     *
     * @throws \RuntimeException on transport errors, HTTP >= 400, or malformed responses.
     */
    public function complete(Agent $agent, array $messages): array
    {
        $payload = [
            'model' => $agent->model,
            'messages' => $messages,
        ];

        if ($agent->temperature !== null) {
            $payload['temperature'] = (float) $agent->temperature;
        }

        if ($agent->max_tokens !== null) {
            $payload['max_tokens'] = $agent->max_tokens;
        }

        $url = rtrim($agent->base_url, '/').'/chat/completions';

        $response = Http::withToken($agent->api_key)
            ->acceptJson()
            ->timeout(max(5, $agent->timeout_seconds))
            ->post($url, $payload);

        if ($response->status() >= 400) {
            throw new \RuntimeException($this->errorMessage($response));
        }

        $content = $this->extractContent($response->json('choices.0.message'));

        if ($content === null) {
            $finish = $response->json('choices.0.finish_reason');
            $body = substr(trim($response->body()), 0, 300);

            throw new \RuntimeException(sprintf(
                'The API returned an empty or malformed response (finish_reason: %s, body: %s).',
                is_string($finish) && $finish !== '' ? $finish : 'n/a',
                $body !== '' ? $body : '(empty)',
            ));
        }

        return [
            'content' => $content,
            'prompt_tokens' => $this->intOrNull($response->json('usage.prompt_tokens')),
            'completion_tokens' => $this->intOrNull($response->json('usage.completion_tokens')),
            'model' => is_string($response->json('model')) ? $response->json('model') : $agent->model,
        ];
    }

    /** Small ping used by the "test connection" button (1 token). */
    public function ping(Agent $agent): array
    {
        $start = microtime(true);

        $result = $this->complete($agent, [
            ['role' => 'user', 'content' => 'ping'],
        ]);

        return $result + ['latency_ms' => (int) round((microtime(true) - $start) * 1000)];
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('error.message');

        if (! is_string($message) || trim($message) === '') {
            $message = $response->json('message');
        }

        return 'HTTP '.$response->status().': '.(is_string($message) && $message !== '' ? $message : 'request failed');
    }

    /**
     * Pull assistant text out of a chat.completions message, tolerating
     * reasoning models (null content + reasoning_content) and providers
     * that return content as an array of parts.
     */
    private function extractContent(mixed $message): ?string
    {
        if (! is_array($message)) {
            return null;
        }

        $content = $message['content'] ?? null;

        if (is_array($content)) {
            $text = collect($content)
                ->filter(fn ($part) => is_array($part) && ($part['type'] ?? null) === 'text')
                ->pluck('text')
                ->filter(fn ($t) => is_string($t) && trim($t) !== '')
                ->implode("\n");

            return $text !== '' ? $text : null;
        }

        if (is_string($content) && trim($content) !== '') {
            return $content;
        }

        foreach (['reasoning_content', 'reasoning'] as $key) {
            if (isset($message[$key]) && is_string($message[$key]) && trim($message[$key]) !== '') {
                return $message[$key];
            }
        }

        return null;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
