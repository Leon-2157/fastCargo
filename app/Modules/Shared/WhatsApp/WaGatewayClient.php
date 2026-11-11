<?php

namespace App\Modules\Shared\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * HTTP client ke waG / wa-gateway (Go service).
 */
class WaGatewayClient
{
    private string $baseUrl;

    private string $secret;

    private int $timeout;

    private bool $enabled;

    public function __construct(?string $baseUrl = null, ?string $secret = null, ?int $timeout = null, ?bool $enabled = null)
    {
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('wagateway.base_url', 'http://localhost:8080')), '/');
        $this->secret = (string) ($secret ?? config('wagateway.secret', ''));
        $this->timeout = (int) ($timeout ?? config('wagateway.timeout', 5));
        $this->enabled = (bool) ($enabled ?? config('wagateway.enabled', true));
    }

    /**
     * Normalisasi nomor telepon ke format digit internasional tanpa + (misal: 6281234567890).
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }

    public function sendText(string $to, string $message, bool $async = false): bool
    {
        if (! $this->enabled || $this->baseUrl === '') {
            return false;
        }

        $target = static::normalizePhone($to);
        $cleanMessage = trim($message);

        if (strlen($target) < 8 || $cleanMessage === '') {
            return false;
        }

        return $this->send([
            'target' => $target,
            'message' => $cleanMessage,
            'async' => $async,
        ]);
    }

    public function getQrCode(): ?string
    {
        if (! $this->enabled || $this->baseUrl === '') {
            return null;
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->connectTimeout(2)
                ->timeout(min($this->timeout, 3))
                ->get("{$this->baseUrl}/qr");

            if ($response->successful()) {
                $qr = $response->json('qr');

                return is_string($qr) && trim($qr) !== '' ? trim($qr) : null;
            }
        } catch (Throwable $e) {
            Log::debug('waG getQr skipped or unreachable', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Memutus sesi WhatsApp aktif dan memicu pembuatan QR pairing baru pada waG.
     */
    public function logout(): bool
    {
        if (! $this->enabled || $this->baseUrl === '') {
            return false;
        }

        return $this->postGateway('/logout');
    }

    /**
     * @return array{reachable: bool, connected: bool, status: string, service: string, user: ?array<string, mixed>, error: ?string}
     */
    public function status(): array
    {
        if (! $this->enabled || $this->baseUrl === '') {
            return [
                'reachable' => false,
                'connected' => false,
                'status' => 'DISABLED',
                'service' => 'waG',
                'user' => null,
                'error' => 'WA Gateway integration is disabled.',
            ];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->connectTimeout(2)
                ->timeout(min($this->timeout, 3))
                ->get("{$this->baseUrl}/internal/status");

            if ($response->status() === 404) {
                $response = Http::withHeaders($this->headers())
                    ->connectTimeout(2)
                    ->timeout(min($this->timeout, 3))
                    ->get("{$this->baseUrl}/status");
            }

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $connected = (bool) ($data['ready'] ?? $data['paired'] ?? $data['connected'] ?? false);

                return [
                    'reachable' => true,
                    'connected' => $connected,
                    'status' => (string) ($data['status'] ?? ($connected ? 'connected' : 'disconnected')),
                    'service' => (string) ($data['service'] ?? 'waG'),
                    'user' => is_array($data['user'] ?? null) ? $data['user'] : null,
                    'error' => null,
                ];
            }

            return [
                'reachable' => true,
                'connected' => false,
                'status' => 'HTTP_'.$response->status(),
                'service' => 'waG',
                'user' => null,
                'error' => "Gateway returned HTTP {$response->status()}",
            ];
        } catch (Throwable $e) {
            Log::warning('waG status check failed', ['error' => $e->getMessage()]);

            return [
                'reachable' => false,
                'connected' => false,
                'status' => 'UNREACHABLE',
                'service' => 'waG',
                'user' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(array $payload): bool
    {
        return $this->postGateway('/send', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postGateway(string $path, array $payload = []): bool
    {
        $target = $payload['target'] ?? ltrim($path, '/');

        try {
            $response = Http::withHeaders($this->headers())
                ->connectTimeout(2)
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}{$path}", $payload);

            if ($response->failed()) {
                Log::warning("waG {$path} returned error status", [
                    'target' => $target,
                    'status' => $response->status(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error("waG {$path} failed", [
                'target' => $target,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return array_filter([
            'Content-Type' => 'application/json',
            'X-Gateway-Secret' => $this->secret !== '' ? $this->secret : null,
        ]);
    }
}
