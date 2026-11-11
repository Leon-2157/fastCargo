<?php

namespace App\Modules\Shared\AI;

use App\Modules\Shared\AI\Contracts\AiProviderInterface;
use App\Modules\Shared\AI\Providers\GeminiProvider;
use App\Modules\Shared\AI\Providers\NineRouterProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Resolve AI provider aktif dari adm_system_settings atau config.
 * Mendukung switching runtime via Admin UI serta fallback otomatis ke Gemini.
 */
class AiManager
{
    private static ?AiProviderInterface $instance = null;

    public static function provider(): AiProviderInterface
    {
        if (static::$instance !== null) {
            return static::$instance;
        }

        $providerName = static::activeProviderKey();

        static::$instance = match ($providerName) {
            '9router' => new NineRouterProvider,
            'gemini' => new GeminiProvider,
            default => throw new InvalidArgumentException("Unknown AI provider: {$providerName}"),
        };

        return static::$instance;
    }

    public static function activeProviderKey(): string
    {
        return static::settingValue('ai_provider', (string) config('ai.default', '9router'));
    }

    public static function activeProviderLabel(): string
    {
        $key = static::activeProviderKey();

        if ($key === 'gemini') {
            $model = static::settingValue('gemini_model', (string) config('ai.providers.gemini.model', 'gemini-2.0-flash'));

            return "Gemini / {$model}";
        }

        $model = static::settingValue('ninerouter_model', (string) config('ai.providers.9router.model', 'ag/gemini-3.8-flash-high'));

        return "9router / {$model}";
    }

    /**
     * Jalankan chat melalui provider aktif, dengan fallback ke Gemini bila 9router gagal dan Gemini key tersedia.
     *
     * @param  array<string, mixed>  $context
     */
    public static function chat(string $prompt, array $context = [], string $systemPrompt = ''): string
    {
        $primaryKey = static::activeProviderKey();

        try {
            return static::provider()->chat($prompt, $context, $systemPrompt);
        } catch (Throwable $e) {
            Log::warning('Primary AI provider chat failed', [
                'provider' => $primaryKey,
                'error' => $e->getMessage(),
            ]);

            if ($primaryKey === '9router' && static::canFallbackToGemini()) {
                try {
                    return (new GeminiProvider)->chat($prompt, $context, $systemPrompt);
                } catch (Throwable $fallbackError) {
                    Log::error('Fallback Gemini chat also failed', [
                        'error' => $fallbackError->getMessage(),
                    ]);
                }
            }

            throw $e;
        }
    }

    /**
     * Buat ringkasan data operasional/dashboard via provider aktif + fallback opsional.
     *
     * @param  array<string, mixed>  $data
     */
    public static function summarize(array $data, string $context = ''): string
    {
        $primaryKey = static::activeProviderKey();

        try {
            return static::provider()->summarize($data, $context);
        } catch (Throwable $e) {
            Log::warning('Primary AI provider summarize failed', [
                'provider' => $primaryKey,
                'error' => $e->getMessage(),
            ]);

            if ($primaryKey === '9router' && static::canFallbackToGemini()) {
                try {
                    return (new GeminiProvider)->summarize($data, $context);
                } catch (Throwable $fallbackError) {
                    Log::error('Fallback Gemini summarize also failed', [
                        'error' => $fallbackError->getMessage(),
                    ]);
                }
            }

            throw $e;
        }
    }

    /**
     * Ambil nilai setting dari adm_system_settings (prioritas) atau fallback default config.
     */
    public static function settingValue(string $key, string $default = ''): string
    {
        $settings = static::runtimeSettings();
        $value = $settings[$key] ?? null;

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return $default;
    }

    /**
     * Reset singleton instance dan cache AI setelah admin mengganti konfigurasi.
     */
    public static function reset(): void
    {
        static::$instance = null;
        Cache::forget('ai_active_provider');
        Cache::forget('ai_runtime_settings');
    }

    /**
     * Ekstrak riwayat percakapan terakhir (maks 6 pesan) untuk multi-turn prompt.
     *
     * @param  array<string, mixed>  $context
     * @return list<array{role: string, content: string}>
     */
    public static function extractHistory(array $context): array
    {
        $raw = $context['history'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $turns = [];
        foreach (array_slice($raw, -6) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $role = ($item['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($item['content'] ?? ''));

            if ($content !== '') {
                $turns[] = ['role' => $role, 'content' => $content];
            }
        }

        return $turns;
    }

    /**
     * Hapus kunci history sebelum context diserialisasi ke JSON.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function stripHistory(array $context): array
    {
        unset($context['history']);

        return $context;
    }

    private static function canFallbackToGemini(): bool
    {
        if (! config('ai.fallback_enabled', true)) {
            return false;
        }

        $geminiKey = static::settingValue('gemini_api_key', (string) config('ai.providers.gemini.api_key', ''));

        return $geminiKey !== '';
    }

    /**
     * @return array<string, string|null>
     */
    private static function runtimeSettings(): array
    {
        try {
            return Cache::remember('ai_runtime_settings', 60, function (): array {
                return DB::table('adm_system_settings')
                    ->whereIn('key', [
                        'ai_provider',
                        'ninerouter_base_url',
                        'ninerouter_api_key',
                        'ninerouter_model',
                        'gemini_api_key',
                        'gemini_model',
                    ])
                    ->pluck('value', 'key')
                    ->all();
            });
        } catch (Throwable) {
            return [];
        }
    }
}
