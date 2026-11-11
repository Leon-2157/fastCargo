<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\AdmSystemSetting;
use App\Modules\Shared\WhatsApp\WaGatewayClient;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class WaGatewayController extends Controller
{
    public function __construct(private readonly WaGatewayClient $waClient) {}

    public function index(): View
    {
        $snapshot = $this->resolveGatewaySnapshot();

        return view('admin.gateway.index', [
            'role' => 'admin',
            'isConnected' => $snapshot['isConnected'],
            'gatewayStatus' => $snapshot['gatewayStatus'],
            'gatewayBaseUrl' => (string) config('wagateway.base_url'),
            'qrSvg' => $snapshot['qrSvg'],
        ]);
    }

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

    public function resetSession(): RedirectResponse
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

        return back()->with('success', 'WhatsApp gateway session reset. Generating a fresh pairing QR code...');
    }

    public function testSend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $sent = $this->waClient->sendText($validated['phone'], $validated['message']);

        if (! $sent) {
            return back()
                ->withInput()
                ->with('error', 'Failed to dispatch test WhatsApp message via waG. Verify WAG_BASE_URL, WAG_SECRET, and gateway session status.');
        }

        AdmSystemSetting::updateOrCreate(
            ['key' => 'wag_connected'],
            [
                'value' => '1',
                'type' => 'boolean',
                'description' => 'WhatsApp gateway connection status',
            ]
        );

        return back()->with('success', "Dispatched live WhatsApp alert to {$validated['phone']}.");
    }

    private function renderQrSvg(string $payload, int $size): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($payload);
    }

    /**
     * @return array{gatewayStatus: array<string, mixed>, isConnected: bool, qrSvg: ?string}
     */
    private function resolveGatewaySnapshot(): array
    {
        $gatewayStatus = $this->waClient->status();

        if ($gatewayStatus['reachable']) {
            AdmSystemSetting::updateOrCreate(
                ['key' => 'wag_connected'],
                [
                    'value' => $gatewayStatus['connected'] ? '1' : '0',
                    'type' => 'boolean',
                    'description' => 'WhatsApp gateway connection status',
                ]
            );
        }

        $isConnected = $gatewayStatus['reachable']
            ? (bool) $gatewayStatus['connected']
            : (bool) (AdmSystemSetting::where('key', 'wag_connected')->value('value') ?? false);

        $qrSvg = null;
        if (($gatewayStatus['reachable'] ?? false) && ! $isConnected) {
            $qrPayload = $this->waClient->getQrCode();
            if ($qrPayload !== null) {
                $qrSvg = $this->renderQrSvg($qrPayload, 220);
            }
        }

        return [
            'gatewayStatus' => $gatewayStatus,
            'isConnected' => $isConnected,
            'qrSvg' => $qrSvg,
        ];
    }
}
