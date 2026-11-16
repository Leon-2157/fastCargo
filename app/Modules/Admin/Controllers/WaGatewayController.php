<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\AdmSystemSetting;
use App\Modules\Shared\Services\AuditLogService;
use App\Modules\Shared\WhatsApp\WaGatewayClient;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class WaGatewayController extends Controller
{
    public function __construct(private readonly WaGatewayClient $waClient) {}

    public function status(): JsonResponse
    {
        $snapshot = $this->resolveGatewaySnapshot();
        $gatewayStatus = $snapshot['gatewayStatus'];

        return response()->json([
            'reachable' => (bool) ($gatewayStatus['reachable'] ?? false),
            'connected' => $snapshot['isConnected'],
            'status' => (string) ($gatewayStatus['status'] ?? 'DISCONNECTED'),
            'service' => (string) ($gatewayStatus['service'] ?? 'waG'),
            'user' => $gatewayStatus['user'] ?? null,
            'qr_svg' => $snapshot['qrSvg'],
            'qr_available' => $snapshot['qrSvg'] !== null,
            'paired_at' => $snapshot['pairedAt'] ?? 'Awaiting scan',
            'error' => $gatewayStatus['error'] ?? null,
        ])->header('Cache-Control', 'no-store');
    }

    public function getQr(): Response|JsonResponse
    {
        $qrPayload = $this->waClient->getQrCode();
        if ($qrPayload === null) {
            return response()->json([
                'available' => false,
                'message' => 'Pairing QR is currently unavailable from waG. Check gateway dashboard or session status.',
            ], 404);
        }

        return response($this->renderQrSvg($qrPayload, 250))
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'no-store');
    }

    public function resetSession(Request $request): RedirectResponse
    {
        if (! $this->waClient->logout()) {
            return back()->with('error', 'Failed to reset waG session. Verify wa-gateway service health and WAG_SECRET.');
        }

        AdmSystemSetting::updateOrCreate(
            ['key' => 'wag_connected'],
            [
                'value' => '0',
                'type' => 'boolean',
                'description' => 'WhatsApp gateway connection status',
            ]
        );

        AuditLogService::record(
            'WA_SESSION_RESET',
            'adm_system_settings',
            'wag_connected',
            ['wag_connected' => true],
            ['wag_connected' => false],
            $request
        );

        return back()->with('success', 'WhatsApp gateway session reset. Generating a fresh pairing QR code...');
    }

    private function renderQrSvg(string $payload, int $size): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($payload);
    }

    /**
     * @return array{gatewayStatus: array<string, mixed>, isConnected: bool, qrSvg: ?string, pairedAt: string}
     */
    private function resolveGatewaySnapshot(): array
    {
        $gatewayStatus = $this->waClient->status();
        $wagSetting = AdmSystemSetting::where('key', 'wag_connected')->first();

        if ($gatewayStatus['reachable']) {
            $wasConnected = (bool) ($wagSetting?->value ?? false);
            $nowConnected = (bool) ($gatewayStatus['connected'] ?? false);

            if ($nowConnected && ! $wasConnected) {
                AdmSystemSetting::updateOrCreate(
                    ['key' => 'wag_paired_at'],
                    [
                        'value' => now()->toIso8601String(),
                        'type' => 'string',
                        'description' => 'WhatsApp pairing timestamp',
                    ]
                );
            } elseif (! $nowConnected) {
                AdmSystemSetting::where('key', 'wag_paired_at')->delete();
            }

            AdmSystemSetting::updateOrCreate(
                ['key' => 'wag_connected'],
                [
                    'value' => $nowConnected ? '1' : '0',
                    'type' => 'boolean',
                    'description' => 'WhatsApp gateway connection status',
                ]
            );
        }

        $isConnected = $gatewayStatus['reachable']
            ? (bool) ($gatewayStatus['connected'] ?? false)
            : (bool) ($wagSetting?->value ?? false);

        $pairedAtRaw = AdmSystemSetting::where('key', 'wag_paired_at')->value('value');
        $pairedAtFormatted = $pairedAtRaw
            ? Carbon::parse($pairedAtRaw)->format('d M Y, H:i').' WIB'
            : ($isConnected && $wagSetting ? $wagSetting->updated_at?->format('d M Y, H:i').' WIB' : 'Awaiting scan');

        $qrSvg = null;
        if (($gatewayStatus['reachable'] ?? false) && ! $isConnected) {
            $qrPayload = $this->waClient->getQrCode();
            if ($qrPayload !== null) {
                $qrSvg = $this->renderQrSvg($qrPayload, 120);
            }
        }

        return [
            'gatewayStatus' => $gatewayStatus,
            'isConnected' => $isConnected,
            'qrSvg' => $qrSvg,
            'pairedAt' => $pairedAtFormatted,
        ];
    }
}
