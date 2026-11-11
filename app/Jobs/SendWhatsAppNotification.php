<?php

namespace App\Jobs;

use App\Modules\Shared\WhatsApp\WaGatewayClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendWhatsAppNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public string $phone,
        public string $message,
        public ?string $contextTag = null,
    ) {}

    public function handle(WaGatewayClient $client): void
    {
        $normalized = WaGatewayClient::normalizePhone($this->phone);
        if (strlen($normalized) < 8 || trim($this->message) === '') {
            return;
        }

        $sent = $client->sendText($normalized, $this->message);

        if (! $sent) {
            Log::warning('WhatsApp notification dispatch failed', [
                'tag' => $this->contextTag,
                'phone' => substr($normalized, 0, 5).'***'.substr($normalized, -2),
                'attempt' => $this->attempts(),
            ]);

            if (! app()->environment('testing') && config('queue.default') !== 'sync' && $this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 30);
            }
        }
    }
}
