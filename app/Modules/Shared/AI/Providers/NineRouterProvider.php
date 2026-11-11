<?php

namespace App\Modules\Shared\AI\Providers;

use App\Modules\Shared\AI\AiManager;
use App\Modules\Shared\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Provider AI via 9router (OpenAI Chat Completions API compatible).
 */
class NineRouterProvider implements AiProviderInterface
{
    private string $baseUrl;

    private string $apiKey;

    private string $model;

    private int $timeout;

    public function __construct(?string $baseUrl = null, ?string $apiKey = null, ?string $model = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? AiManager::settingValue('ninerouter_base_url', (string) config('ai.providers.9router.base_url', '')), '/');
        $this->apiKey = $apiKey ?? AiManager::settingValue('ninerouter_api_key', (string) config('ai.providers.9router.api_key', ''));
        $this->model = $model ?? AiManager::settingValue('ninerouter_model', (string) config('ai.providers.9router.model', 'ag/gemini-3.8-flash-high'));
        $this->timeout = (int) config('ai.timeout', 30);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function chat(string $prompt, array $context = [], string $systemPrompt = ''): string
    {
        if ($this->baseUrl === '' || $this->apiKey === '') {
            throw new RuntimeException('9router provider is missing base URL or API key.');
        }

        $messages = [];
        if ($systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        $pageData = AiManager::stripHistory($context);
        if (! empty($pageData)) {
            $messages[] = ['role' => 'system', 'content' => "Current page context:\n".json_encode($pageData, JSON_PRETTY_PRINT)];
        }

        foreach (AiManager::extractHistory($context) as $turn) {
            $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages,
                'stream' => false,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("9router request failed with HTTP {$response->status()}.");
        }

        $content = $response->json('choices.0.message.content');
        if (is_string($content) && trim($content) !== '') {
            return trim($content);
        }

        $streamed = $this->parseSseFallback($response->body());
        if ($streamed !== null) {
            return $streamed;
        }

        throw new RuntimeException('9router returned an empty response payload.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function summarize(array $data, string $context = ''): string
    {
        $prompt = "Summarize this FastCargo logistics dataset into concise, actionable bullet points:\n"
            .json_encode($data, JSON_PRETTY_PRINT);

        return $this->chat($prompt, [], $context);
    }

    public function name(): string
    {
        return '9router (FastCargo)';
    }

    private function parseSseFallback(string $body): ?string
    {
        if (! str_contains($body, 'data: ')) {
            return null;
        }

        $text = '';
        foreach (explode("\n", $body) as $line) {
            if (str_starts_with($line, 'data: ') && trim($line) !== 'data: [DONE]') {
                $json = json_decode(substr($line, 6), true);
                if (is_array($json)) {
                    $text .= (string) ($json['choices'][0]['delta']['content'] ?? '');
                }
            }
        }

        $trimmed = trim($text);

        return $trimmed !== '' ? $trimmed : null;
    }
}
