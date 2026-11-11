<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppNotification;
use App\Livewire\BookingWizard;
use App\Modules\Shared\AI\AiManager;
use App\Modules\Shared\Models\AdmSystemSetting;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\User;
use App\Modules\Shared\WhatsApp\WaGatewayClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class Phase2WaGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        AiManager::reset();
    }

    public function test_wag_client_normalizes_phones_and_parses_gateway_endpoints(): void
    {
        $this->assertSame('6281234567890', WaGatewayClient::normalizePhone('+62 812-3456-7890'));
        $this->assertSame('6281234567890', WaGatewayClient::normalizePhone('081234567890'));

        Http::fake([
            'wag.test/send' => Http::response(['status' => true, 'messageId' => 'msg-123'], 200),
            'wag.test/internal/status' => Http::response([
                'service' => 'FastCargo Logistics WhatsApp Gateway',
                'status' => 'connected',
                'paired' => true,
                'ready' => true,
                'user' => ['id' => '6281234567890@s.whatsapp.net', 'phone' => '6281234567890', 'name' => 'FastCargo Official'],
            ], 200),
            'wag.test/qr' => Http::response(['qr' => '2@PAIRING-TOKEN'], 200),
        ]);

        $client = new WaGatewayClient('http://wag.test', 'secret-123', 5, true);

        $this->assertTrue($client->sendText('081234567890', 'Cargo alert!'));
        $status = $client->status();
        $this->assertTrue($status['reachable']);
        $this->assertTrue($status['connected']);
        $this->assertSame('connected', $status['status']);
        $this->assertSame('6281234567890', $status['user']['phone']);
        $this->assertSame('2@PAIRING-TOKEN', $client->getQrCode());

        Http::assertSent(function ($request): bool {
            if (! str_ends_with($request->url(), '/send') && ! str_ends_with($request->url(), '/internal/status')) {
                return true;
            }

            if (! $request->hasHeader('X-Gateway-Secret', 'secret-123')) {
                return false;
            }

            if (str_ends_with($request->url(), '/internal/status')) {
                return true;
            }

            return ($request['target'] ?? null) === '6281234567890'
                && ($request['message'] ?? null) === 'Cargo alert!';
        });
    }

    public function test_wag_client_falls_back_to_public_status_when_internal_status_returns_404(): void
    {
        Http::fake([
            'wag.test/internal/status' => Http::response(['error' => 'not found'], 404),
            'wag.test/status' => Http::response([
                'service' => 'waG Legacy',
                'status' => 'connected',
                'ready' => true,
            ], 200),
        ]);

        $client = new WaGatewayClient('http://wag.test', 'secret-123', 5, true);
        $status = $client->status();

        $this->assertTrue($status['reachable']);
        $this->assertTrue($status['connected']);
        $this->assertSame('waG Legacy', $status['service']);
    }

    public function test_admin_wa_gateway_station_shows_real_status_and_updates_connection_on_test_dispatch(): void
    {
        config(['wagateway.base_url' => 'http://wag.test']);
        $admin = User::where('email', 'admin@fastcargo.internal')->firstOrFail();

        Http::fake([
            'wag.test/internal/status' => Http::response(['paired' => false, 'ready' => false, 'status' => 'qr_ready', 'service' => 'waG'], 200),
            'wag.test/qr' => Http::response(['qr' => '2@LIVE-QR-TOKEN'], 200),
            'wag.test/send' => Http::sequence()
                ->push(['status' => false, 'error' => 'Not paired'], 502)
                ->push(['status' => true, 'messageId' => 'msg-ok'], 200),
        ]);

        $index = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->get('http://admin.fastcargo.local/wa-gateway');

        $index->assertStatus(200);
        $index->assertSee('Disconnected / Pairing Needed');
        $index->assertSee('<svg', false);

        $qrResponse = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->get('http://admin.fastcargo.local/wa-gateway/qr');

        $qrResponse->assertStatus(200);
        $qrResponse->assertHeader('Content-Type', 'image/svg+xml');

        // Failed send must NOT fake connected state
        $failSend = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('http://admin.fastcargo.local/wa-gateway/test', [
                'phone' => '+62811000003',
                'message' => 'Test message failure',
            ]);

        $failSend->assertRedirect();
        $failSend->assertSessionHas('error');
        $this->assertSame('0', AdmSystemSetting::where('key', 'wag_connected')->value('value'));

        // Successful send marks wag_connected = 1
        $okSend = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('http://admin.fastcargo.local/wa-gateway/test', [
                'phone' => '+62811000003',
                'message' => 'Test message success',
            ]);

        $okSend->assertRedirect();
        $okSend->assertSessionHas('success');
        $this->assertSame('1', AdmSystemSetting::where('key', 'wag_connected')->value('value'));
    }

    public function test_admin_wa_gateway_status_polling_and_session_reset_work(): void
    {
        config(['wagateway.base_url' => 'http://wag.test']);
        $admin = User::where('email', 'admin@fastcargo.internal')->firstOrFail();

        Http::fake([
            'wag.test/internal/status' => Http::sequence()
                ->push(['paired' => false, 'ready' => false, 'status' => 'qr_ready', 'service' => 'waG'], 200)
                ->push([
                    'paired' => true,
                    'ready' => true,
                    'status' => 'connected',
                    'service' => 'waG',
                    'user' => ['id' => '6285836290424@s.whatsapp.net', 'phone' => '6285836290424', 'name' => 'FastCargo Official'],
                ], 200),
            'wag.test/qr' => Http::response(['qr' => '2@POLLING-QR'], 200),
            'wag.test/logout' => Http::response(['status' => true, 'message' => 'logged out'], 200),
        ]);

        $qrPoll = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->getJson('http://admin.fastcargo.local/wa-gateway/status');

        $qrPoll->assertStatus(200)
            ->assertJson([
                'reachable' => true,
                'connected' => false,
                'status' => 'qr_ready',
                'qr_available' => true,
            ]);
        $this->assertStringContainsString('<svg', (string) $qrPoll->json('qr_svg'));

        $connectedPoll = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->getJson('http://admin.fastcargo.local/wa-gateway/status');

        $connectedPoll->assertStatus(200)
            ->assertJson([
                'reachable' => true,
                'connected' => true,
                'status' => 'connected',
                'qr_available' => false,
                'user' => ['phone' => '6285836290424'],
            ]);
        $this->assertSame('1', AdmSystemSetting::where('key', 'wag_connected')->value('value'));

        $resetResp = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('http://admin.fastcargo.local/wa-gateway/reset');

        $resetResp->assertRedirect();
        $resetResp->assertSessionHas('success');
        $this->assertSame('0', AdmSystemSetting::where('key', 'wag_connected')->value('value'));

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/logout'));
    }

    public function test_booking_and_operator_milestones_dispatch_whatsapp_notifications(): void
    {
        Queue::fake();

        $customer = User::where('email', 'customer@example.com')->firstOrFail();
        $operator = User::where('email', 'operator@fastcargo.com')->firstOrFail();

        Livewire::actingAs($customer, 'customer')
            ->test(BookingWizard::class)
            ->set('currentStep', 4)
            ->call('submitBooking')
            ->assertRedirect();

        Queue::assertPushed(SendWhatsAppNotification::class, function (SendWhatsAppNotification $job): bool {
            return $job->contextTag === 'booking_created'
                && $job->phone === '62811000003'
                && str_contains($job->message, 'FastCargo Booking Confirmed');
        });

        $seededBooking = OpsCargoBooking::where('tracking_number', 'RPN-20261106-001')->firstOrFail();

        $this->actingAs($operator, 'operator')
            ->withSession(['two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post("http://operator.fastcargo.local/tracking/{$seededBooking->id}/event", [
                'event_name' => 'Departed Tanjung Priok Port',
                'current_status' => 'SAILING',
                'latitude' => -6.1050,
                'longitude' => 106.8847,
            ])
            ->assertRedirect();

        Queue::assertPushed(SendWhatsAppNotification::class, function (SendWhatsAppNotification $job): bool {
            return $job->contextTag === 'booking_status_updated'
                && str_contains($job->message, 'RPN-20261106-001')
                && str_contains($job->message, 'SAILING');
        });

        $this->actingAs($operator, 'operator')
            ->withSession(['two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post("http://operator.fastcargo.local/epod/{$seededBooking->id}", [
                'recipient_name' => 'Budi Santoso',
                'recipient_phone' => '+6281999888777',
                'latitude' => 1.2644,
                'longitude' => 103.8222,
            ])
            ->assertRedirect();

        Queue::assertPushed(SendWhatsAppNotification::class, function (SendWhatsAppNotification $job): bool {
            return $job->contextTag === 'booking_delivered_recipient'
                && $job->phone === '6281999888777'
                && str_contains($job->message, 'DELIVERED');
        });
    }

    public function test_internal_wag_webhooks_enforce_secret_and_answer_tracking_and_ai_queries(): void
    {
        Queue::fake();

        // Missing or wrong secret is strictly blocked
        $this->postJson('/api/internal/wag/webhook', [
            'sender' => '6281234567890',
            'message' => 'TRACK RPN-20261106-001',
        ])->assertStatus(403);

        $this->withHeader('X-Gateway-Secret', 'wrong-secret')
            ->postJson('/api/internal/wag/webhook', [
                'sender' => '6281234567890',
                'message' => 'TRACK RPN-20261106-001',
            ])->assertStatus(403);

        // Status webhook with X-Gateway-Secret updates connection state
        $this->withHeader('X-Gateway-Secret', 'testing-wag-secret')
            ->postJson('/api/internal/wag/status', ['ready' => true])
            ->assertStatus(200)
            ->assertJson(['status' => 'ok', 'connected' => true]);

        $this->assertSame('1', AdmSystemSetting::where('key', 'wag_connected')->value('value'));

        // Tracking query bot lookup + idempotency guard
        $trackResp = $this->withHeaders([
            'X-Gateway-Secret' => 'testing-wag-secret',
            'Idempotency-Key' => 'evt-track-001',
        ])->postJson('/api/internal/wag/webhook', [
            'event_id' => 'evt-track-001',
            'sender' => '081234567890',
            'message' => 'Cek resi RPN-20261106-001 dong',
        ]);

        $trackResp->assertStatus(200)
            ->assertJson(['status' => 'ok', 'intent' => 'track_lookup']);

        Queue::assertPushed(SendWhatsAppNotification::class, function (SendWhatsAppNotification $job): bool {
            return $job->contextTag === 'wa_bot_reply'
                && $job->phone === '6281234567890'
                && str_contains($job->message, 'RPN-20261106-001')
                && str_contains($job->message, 'Tanjung Priok Port');
        });

        // Replayed event with same Idempotency-Key must be deduplicated
        $dupResp = $this->withHeaders([
            'X-Gateway-Secret' => 'testing-wag-secret',
            'Idempotency-Key' => 'evt-track-001',
        ])->postJson('/api/internal/wag/webhook', [
            'event_id' => 'evt-track-001',
            'sender' => '081234567890',
            'message' => 'Cek resi RPN-20261106-001 dong',
        ]);

        $dupResp->assertStatus(200)->assertJson(['status' => 'duplicate']);
        Queue::assertPushed(SendWhatsAppNotification::class, 1);

        // Natural language question routes through AI assistant when X-Internal-Secret is used
        AdmSystemSetting::updateOrCreate(['key' => 'ai_provider'], ['value' => '9router', 'type' => 'string']);
        AdmSystemSetting::updateOrCreate(['key' => 'ninerouter_base_url'], ['value' => 'http://9router.test/v1', 'type' => 'string']);
        AdmSystemSetting::updateOrCreate(['key' => 'ninerouter_api_key'], ['value' => 'test-9r-key', 'type' => 'string']);
        AiManager::reset();

        Http::fake([
            '9router.test/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'For LCL cargo, prepare your packing list and HS code.']]],
            ], 200),
        ]);

        $aiResp = $this->flushHeaders()
            ->withHeaders([
                'X-Internal-Secret' => 'testing-internal-secret',
                'Idempotency-Key' => 'evt-ai-002',
            ])
            ->postJson('/api/internal/wag/webhook', [
                'event_id' => 'evt-ai-002',
                'sender' => '+6281234567890',
                'message' => 'What documents are needed for ASEAN LCL cargo?',
            ]);

        $aiResp->assertStatus(200)->assertJson([
            'status' => 'ok',
            'intent' => 'ai_support',
        ]);

        Queue::assertPushed(SendWhatsAppNotification::class, 2);
    }
}
