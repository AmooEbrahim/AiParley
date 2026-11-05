<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Provider;
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

        $response = $this->post(
            rtrim($this->baseUrlFor($agent), '/').'/chat/completions',
            $this->apiKeyFor($agent),
            max(5, (int) $agent->timeout_seconds),
            $payload,
        );

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

    /**
     * Fetch the model catalog from a provider's OpenAI-compatible /models
     * endpoint. Normalizes OpenAI/OpenRouter (`data[]`) and Ollama-style
     * (`models[]`) payloads into a common shape with per-1M pricing.
     *
     * @return array<int, array{id: string, name: string, context_length: ?int, price_in_1m: ?float, price_out_1m: ?float}>
     */
    public function listModels(Provider $provider): array
    {
        if (! preg_match('#^https?://#i', (string) $provider->base_url)) {
            throw new \RuntimeException("Provider \"{$provider->name}\" has no valid base URL.");
        }

        $response = Http::acceptJson()
            ->when((string) $provider->api_key !== '', fn ($req) => $req->withToken((string) $provider->api_key))
            ->timeout(30)
            ->get(rtrim($provider->base_url, '/').'/models');

        if ($response->status() >= 400) {
            throw new \RuntimeException($this->errorMessage($response));
        }

        $items = $response->json('data') ?? $response->json('models');

        if (! is_array($items)) {
            throw new \RuntimeException('The provider returned a malformed /models response.');
        }

        return collect($items)
            ->map(fn ($item) => $this->normalizeModel($item))
            ->filter()
            ->unique('id')
            ->sortBy('id', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function normalizeModel(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $id = $item['id'] ?? $item['name'] ?? null;

        if (! is_string($id) || trim($id) === '') {
            return null;
        }

        return [
            'id' => $id,
            'name' => is_string($item['name'] ?? null) && $item['name'] !== '' ? $item['name'] : $id,
            'context_length' => is_numeric($item['context_length'] ?? null) ? (int) $item['context_length'] : null,
            'price_in_1m' => $this->pricePerMillion($item['pricing']['prompt'] ?? null),
            'price_out_1m' => $this->pricePerMillion($item['pricing']['completion'] ?? null),
        ];
    }

    /** Convert an OpenRouter per-token price string into per-1M-tokens float. */
    private function pricePerMillion(mixed $perToken): ?float
    {
        if (! is_numeric($perToken)) {
            return null;
        }

        return round(((float) $perToken) * 1_000_000, 6);
    }

    private function baseUrlFor(Agent $agent): string
    {
        $url = $agent->resolveBaseUrl();

        if (! is_string($url) || trim($url) === '' || ! preg_match('#^https?://#i', $url)) {
            throw new \RuntimeException(
                "Agent \"{$agent->name}\" has no valid endpoint URL. Open Agents and check its provider's base URL.",
            );
        }

        return $url;
    }

    private function apiKeyFor(Agent $agent): string
    {
        return (string) $agent->resolveApiKey();
    }

    private function post(string $url, string $apiKey, int $timeoutSeconds, array $payload): Response
    {
        return Http::acceptJson()
            ->when($apiKey !== '', fn ($req) => $req->withToken($apiKey))
            ->timeout($timeoutSeconds)
            ->post($url, $payload);
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
