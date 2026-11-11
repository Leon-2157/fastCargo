<?php

namespace App\Http\Controllers\Api;

use App\Jobs\SendWhatsAppNotification;
use App\Modules\Shared\AI\AiManager;
use App\Modules\Shared\Models\AdmSystemSetting;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\WhatsApp\WaGatewayClient;
use App\Modules\Shared\WhatsApp\WaNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class WaGatewayWebhookController extends Controller
{
    /**
     * Handle status update callback dari waG (atau delegasikan jika payload pesan masuk).
     */
    public function handle(Request $request): JsonResponse
    {
        if ($request->filled('message') && ($request->filled('sender') || $request->filled('from'))) {
            return $this->handleIncoming($request);
        }

        if ($request->hasAny(['ready', 'connected', 'paired'])) {
            $connected = $request->boolean('ready', $request->boolean('connected', $request->boolean('paired')));

            AdmSystemSetting::updateOrCreate(
                ['key' => 'wag_connected'],
                [
                    'value' => $connected ? '1' : '0',
                    'type' => 'boolean',
                    'description' => 'WhatsApp gateway connection status',
                ]
            );

            return response()->json([
                'status' => 'ok',
                'connected' => $connected,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle incoming WhatsApp customer message / bot tracking query dari wa-gateway webhook forwarder.
     */
    public function handleIncoming(Request $request): JsonResponse
    {
        $rawSender = (string) ($request->input('sender') ?? $request->input('from') ?? '');
        $sender = WaGatewayClient::normalizePhone($rawSender);
        $message = trim((string) ($request->input('message') ?? $request->input('text') ?? ''));
        $eventId = trim((string) ($request->header('Idempotency-Key') ?? $request->input('event_id') ?? ''));

        if (strlen($sender) < 8) {
            return response()->json(['status' => 'error', 'error' => 'Invalid sender phone number.'], 422);
        }

        if ($message === '') {
            return response()->json(['status' => 'ignored']);
        }

        if ($eventId !== '' && ! Cache::add("wag_webhook_event:{$eventId}", true, now()->addHours(24))) {
            return response()->json([
                'status' => 'duplicate',
                'event_id' => $eventId,
            ]);
        }

        $botResult = $this->resolveBotReply($message);

        SendWhatsAppNotification::dispatch($sender, $botResult['reply'], 'wa_bot_reply');

        return response()->json([
            'status' => 'ok',
            'intent' => $botResult['intent'],
            'reply' => $botResult['reply'],
        ]);
    }

    /**
     * @return array{intent: string, reply: string}
     */
    private function resolveBotReply(string $message): array
    {
        if (preg_match('/\b(RPN-\d{8}-[A-Z0-9]+)\b/i', $message, $matches) === 1) {
            $trackingNumber = strtoupper($matches[1]);

            return [
                'intent' => 'track_lookup',
                'reply' => $this->formatTrackingLookupReply($trackingNumber),
            ];
        }

        if (preg_match('/^(hi|hello|halo|hai|menu|help|info|bot)\b/i', $message) === 1) {
            return [
                'intent' => 'menu',
                'reply' => $this->helpMenuReply(),
            ];
        }

        try {
            $systemPrompt = 'You are FastCargo WhatsApp Support Bot for ASEAN cross-border freight (sea, air, land cargo). '
                .'Answer briefly in plain WhatsApp-friendly text (max 4 sentences). '
                .'If user wants shipment tracking, ask for their waybill format RPN-YYYYMMDD-XXXX. '
                .'Never fabricate shipment statuses, prices, or internal data.';

            $aiReply = AiManager::chat($message, ['channel' => 'whatsapp_bot'], $systemPrompt);

            return [
                'intent' => 'ai_support',
                'reply' => $aiReply,
            ];
        } catch (Throwable $e) {
            Log::warning('WA bot AI fallback failed, returning static help menu', ['error' => $e->getMessage()]);

            return [
                'intent' => 'help_fallback',
                'reply' => $this->helpMenuReply(),
            ];
        }
    }

    private function formatTrackingLookupReply(string $trackingNumber): string
    {
        $booking = OpsCargoBooking::where('tracking_number', $trackingNumber)
            ->with([
                'route.originPort',
                'route.destinationPort',
                'trackingEvents' => fn ($q) => $q->latest('event_timestamp')->take(1),
            ])
            ->first();

        if (! $booking) {
            return "*[FastCargo Tracking]*\n"
                ."Waybill *{$trackingNumber}* was not found.\n"
                .'Please double-check your tracking code (example: RPN-20261106-001).';
        }

        $origin = $booking->route?->originPort?->name ?? ($booking->route?->origin_port_id ?? 'Origin');
        $destination = $booking->route?->destinationPort?->name ?? ($booking->route?->destination_port_id ?? 'Destination');
        $latestEvent = $booking->trackingEvents->first();
        $latestLine = $latestEvent
            ? "\nLatest Update: {$latestEvent->event_name} (".$latestEvent->event_timestamp?->format('d M Y H:i').')'
            : '';

        return "*[FastCargo Tracking]*\n"
            ."Waybill: *{$booking->tracking_number}*\n"
            ."Route: {$origin} -> {$destination}\n"
            ."Current Status: *{$booking->cargo_status}*{$latestLine}\n"
            .'Live Link: '.WaNotificationService::trackingUrl($booking->tracking_number);
    }

    private function helpMenuReply(): string
    {
        return "*FastCargo Automated Assistant*\n"
            ."Send a waybill number to check shipment status:\n"
            ."- Example: *TRACK RPN-20261106-001*\n"
            ."- Or ask any question about ASEAN sea/air/land freight & customs.\n"
            .'Portal: '.rtrim((string) config('app.portal_urls.landing', 'http://fastcargo.local:8080'), '/');
    }
}
