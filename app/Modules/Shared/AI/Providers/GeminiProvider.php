<?php

namespace App\Modules\Shared\AI\Providers;

use App\Modules\Shared\AI\AiManager;
use App\Modules\Shared\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Provider AI langsung via Google Gemini REST API.
 */
class GeminiProvider implements AiProviderInterface
{
    private string $apiKey;

    private string $model;

    private int $timeout;

    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? AiManager::settingValue('gemini_api_key', (string) config('ai.providers.gemini.api_key', ''));
        $this->model = $model ?? AiManager::settingValue('gemini_model', (string) config('ai.providers.gemini.model', 'gemini-2.0-flash'));
        $this->timeout = (int) config('ai.timeout', 30);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function chat(string $prompt, array $context = [], string $systemPrompt = ''): string
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('Gemini provider API key is not configured.');
        }

        $contents = [];

        foreach (AiManager::extractHistory($context) as $turn) {
            $contents[] = [
                'role' => $turn['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $turn['content']]],
            ];
        }

        $userParts = [];
        $pageData = AiManager::stripHistory($context);
        if (! empty($pageData)) {
            $userParts[] = ['text' => "Context:\n".json_encode($pageData, JSON_PRETTY_PRINT)];
        }
        $userParts[] = ['text' => $prompt];
        $contents[] = ['role' => 'user', 'parts' => $userParts];

        $payload = ['contents' => $contents];
        if ($systemPrompt !== '') {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemPrompt]],
            ];
        }

        $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/models/{$this->model}:generateContent", $payload);

        if ($response->failed()) {
            throw new RuntimeException("Gemini API request failed with HTTP {$response->status()}.");
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Gemini returned an empty response payload.');
        }

        return trim($text);
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
        return 'Gemini';
    }
}
