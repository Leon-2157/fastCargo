<?php

namespace Tests\Feature;

use App\Modules\Shared\Models\AdmEscrowLedger;
use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\AdmSystemSetting;
use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\PaymentTransaction;
use App\Modules\Shared\Models\Port;
use App\Modules\Shared\Models\Route;
use App\Modules\Shared\Models\User;
use App\Modules\Shared\Services\PayGPaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SgpayMacroDroidIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_GOPAY_STATIC_QRIS = '00020101021126610014COM.GO-JEK.WWW01189360091400001234560210GOPAY123450303UMI51440014ID.CO.QRIS.WWW0215ID10200300400500303UMI5204541153033605802ID5914TOKO MAJU JAYA6015JAKARTA SELATAN6105121906304822D';

    private User $admin;

    private User $customer;

    private Company $company;

    private Route $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Finance Admin',
            'username' => 'finadmin_'.Str::random(5),
            'email' => 'finadmin_'.Str::random(5).'@fastcargo.test',
            'password' => Hash::make('AdminPass123!'),
            'user_type' => 'ADMIN_USER',
            'status' => 'ACTIVE',
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_enabled_at' => now(),
        ]);

        $this->customer = User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'PT Pelayaran Test',
            'username' => 'cust_'.Str::random(5),
            'email' => 'cust_'.Str::random(5).'@fastcargo.test',
            'password' => Hash::make('Secret123!'),
            'user_type' => 'CORPORATE_USER',
            'status' => 'ACTIVE',
            'phone' => '628111222333',
        ]);

        $this->company = Company::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->customer->id,
            'company_name' => 'PT Pelayaran Test Tbk',
            'company_address' => 'Jakarta Utara, Indonesia',
            'npwp' => '01.234.567.8-099.000',
            'nib' => '9120001234567',
        ]);

        $origin = Port::firstOrCreate(
            ['id' => 'IDTPP'],
            ['name' => 'Tanjung Priok', 'city' => 'Jakarta', 'country_code' => 'ID', 'port_type' => 'SEA', 'latitude' => -6.10, 'longitude' => 106.88]
        );
        $dest = Port::firstOrCreate(
            ['id' => 'SGSIN'],
            ['name' => 'PSA Singapore', 'city' => 'Singapore', 'country_code' => 'SG', 'port_type' => 'SEA', 'latitude' => 1.26, 'longitude' => 103.82]
        );

        $this->route = Route::firstOrCreate(
            ['origin_port_id' => $origin->id, 'destination_port_id' => $dest->id],
            ['id' => Str::uuid()->toString(), 'transport_mode' => 'SEA', 'transit_time_days' => 2, 'base_rate' => 150000]
        );
    }

    private function createUnpaidInvoice(float $amount = 150000, ?User $owner = null, ?Company $company = null): AdmInvoice
    {
        $resolvedUser = $owner ?? $this->customer;
        $resolvedCompany = $company ?? $this->company;

        $booking = OpsCargoBooking::create([
            'id' => Str::uuid()->toString(),
            'tracking_number' => 'FC-'.strtoupper(Str::random(8)),
            'shipper_id' => $resolvedUser->id,
            'company_id' => $resolvedCompany->id,
            'route_id' => $this->route->id,
            'booking_type' => 'RETAIL_LCL',
            'cargo_type' => 'GENERAL_CARGO',
            'total_weight_kg' => 100,
            'total_volume_cbm' => 1,
            'declared_goods_value' => $amount,
            'currency' => 'IDR',
            'cargo_status' => 'BOOKED',
        ]);

        return AdmInvoice::create([
            'id' => Str::uuid()->toString(),
            'invoice_number' => 'INV-SG-'.strtoupper(Str::random(6)),
            'company_id' => $resolvedCompany->id,
            'cargo_booking_id' => $booking->id,
            'total_invoice_amount' => $amount,
            'currency' => 'IDR',
            'payment_status' => AdmInvoice::STATUS_UNPAID,
            'due_date' => now()->addDays(7),
        ]);
    }

    public function test_admin_rejects_invalid_emvco_or_corrupt_crc_static_qris(): void
    {
        $badCrc = '00020101021126580014ID.DANA.WWW6304ABCD';

        $resp = $this->actingAs($this->admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->put('http://admin.fastcargo.local/settings', [
                'payg_active_mode' => 'sgpay',
                'payg_sgpay_static_qr' => $badCrc,
            ]);

        $resp->assertSessionHasErrors('payg_sgpay_static_qr');
        $this->assertDatabaseMissing('adm_system_settings', [
            'key' => 'payg_sgpay_static_qr',
            'value' => $badCrc,
        ]);

        $nonQris = $this->actingAs($this->admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->put('http://admin.fastcargo.local/settings', [
                'payg_active_mode' => 'sgpay',
                'payg_sgpay_static_qr' => 'https://not-a-qris.example.com/pay',
            ]);

        $nonQris->assertSessionHasErrors('payg_sgpay_static_qr');
    }

    public function test_admin_saves_gopay_static_qris_and_settings_page_displays_svg_and_merchant_badges(): void
    {
        Http::fake([
            '*/api/*' => Http::response(['success' => true], 200),
        ]);

        $update = $this->actingAs($this->admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->put('http://admin.fastcargo.local/settings', [
                'payg_active_mode' => 'sgpay',
                'payg_sgpay_static_qr' => self::VALID_GOPAY_STATIC_QRIS."\n",
                'payg_secret' => 'custom-macrodroid-secret-999',
            ]);

        $update->assertSessionHasNoErrors();
        $update->assertRedirect();

        $this->assertEquals(
            self::VALID_GOPAY_STATIC_QRIS,
            AdmSystemSetting::get('payg_sgpay_static_qr')
        );

        $viewResp = $this->actingAs($this->admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->get('http://admin.fastcargo.local/settings');

        $viewResp->assertStatus(200);
        $viewResp->assertSee('TOKO MAJU JAYA');
        $viewResp->assertSee('JAKARTA SELATAN');
        $viewResp->assertSee('GoPay / GoBiz');
        $viewResp->assertSee('ID1020030040050');
        $viewResp->assertSee('CRC-16 Valid');
        $viewResp->assertSee('<svg', false);
        $viewResp->assertDontSee('custom-macrodroid-secret-999');
    }

    public function test_admin_qr_preview_reveal_secret_and_webhook_dryrun_endpoints(): void
    {
        AdmSystemSetting::set('payg_secret', 'admin-revealed-secret-123');
        $invoice = $this->createUnpaidInvoice(150000);

        PaymentTransaction::create([
            'invoice_id' => $invoice->id,
            'payer_user_id' => $this->customer->id,
            'transaction_ref' => 'SGPAY-DRYRUN-01',
            'payment_gateway' => 'SGPAY',
            'payment_channel' => 'QRIS',
            'amount' => 150342,
            'payment_status' => PaymentTransaction::STATUS_PENDING,
        ]);

        $previewResp = $this->actingAs($this->admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->postJson('http://admin.fastcargo.local/settings/payment/qr-preview', [
                'qris' => self::VALID_GOPAY_STATIC_QRIS,
            ]);

        $previewResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'valid' => true,
                'metadata' => [
                    'merchant_name' => 'TOKO MAJU JAYA',
                    'merchant_city' => 'JAKARTA SELATAN',
                    'acquirer_label' => 'GoPay / GoBiz',
                    'nmid' => 'ID1020030040050',
                ],
            ]);
        $this->assertStringContainsString('<svg', (string) $previewResp->json('svg'));

        $secretResp = $this->actingAs($this->admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->postJson('http://admin.fastcargo.local/settings/payment/reveal-secret');

        $secretResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'secret' => 'admin-revealed-secret-123',
            ]);

        $dryRunOk = $this->actingAs($this->admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->postJson('http://admin.fastcargo.local/settings/payment/webhook-test', [
                'notification_text' => 'Kamu menerima pembayaran QRIS sebesar Rp150.342',
            ]);

        $dryRunOk->assertStatus(200)
            ->assertJson([
                'success' => true,
                'amount' => 150342,
                'matching_invoices' => 1,
            ]);

        // Dry run must NOT settle the invoice
        $this->assertEquals(AdmInvoice::STATUS_UNPAID, $invoice->fresh()->payment_status);

        $dryRunBad = $this->actingAs($this->admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->postJson('http://admin.fastcargo.local/settings/payment/webhook-test', [
                'notification_text' => 'Promo diskon hari ini',
            ]);

        $dryRunBad->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_payg_dynamic_payload_preserves_real_merchant_tags_over_synthetic_daemon_fallback(): void
    {
        AdmSystemSetting::set('payg_sgpay_static_qr', self::VALID_GOPAY_STATIC_QRIS);
        $invoice = $this->createUnpaidInvoice(150000);
        $service = app(PayGPaymentGatewayService::class);

        // Case 1: Daemon returns synthetic ID.DANA.WWW QRIS -> Laravel must override with real GoPay merchant tags
        Http::fake([
            '*/api/checkout/create' => Http::response([
                'success' => true,
                'data' => [
                    'transaction_ref' => 'SGPAY-DAEMON-SYNTHETIC',
                    'payable_amount' => 150342,
                    'unique_code' => 342,
                    'engine' => 'sgpay',
                    'channel' => 'QRIS',
                    'qr_string' => '00020101021226580014ID.DANA.WWW011893600911002233445552045411530336054061503425802ID5925FASTCARGO ASEAN LOGISTICS6007JAKARTA63041234',
                    'is_mock' => true,
                    'expires_at' => now()->addMinutes(15)->toIso8601String(),
                ],
            ], 200),
        ]);

        $checkout = $service->generateCheckout($invoice, $this->customer);

        $this->assertFalse($checkout['is_mock']);
        $this->assertEquals('TOKO MAJU JAYA', $checkout['merchant_name']);
        $this->assertEquals('JAKARTA SELATAN', $checkout['merchant_city']);
        $this->assertEquals('GoPay / GoBiz', $checkout['acquirer_label']);
        $this->assertStringStartsWith('000201010212', $checkout['qr_string']);

        $parsed = $service->parseEmvcoMetadata($checkout['qr_string']);
        $this->assertTrue($parsed['valid']);
        $this->assertTrue($parsed['crc_valid']);
        $this->assertEquals('12', $parsed['poi_method']);
        $this->assertEquals('150342', $parsed['amount']);
        $this->assertEquals('COM.GO-JEK.WWW', $parsed['acquirer_domain']);
        $this->assertEquals('ID1020030040050', $parsed['nmid']);
        $this->assertEquals('TOKO MAJU JAYA', $parsed['merchant_name']);
        $this->assertEquals('JAKARTA SELATAN', $parsed['merchant_city']);
    }

    public function test_verify_payg_signature_supports_triple_auth_and_rejects_unauthorized(): void
    {
        AdmSystemSetting::set('payg_secret', 'macrodroid-super-secret-777');
        $invoice = $this->createUnpaidInvoice(200000);

        $tx1 = PaymentTransaction::create([
            'invoice_id' => $invoice->id,
            'payer_user_id' => $this->customer->id,
            'transaction_ref' => 'SGPAY-AUTH-01',
            'payment_gateway' => 'SGPAY',
            'payment_channel' => 'QRIS',
            'amount' => 200111,
            'payment_status' => PaymentTransaction::STATUS_PENDING,
        ]);

        $payload = json_encode(['notification_text' => 'Transfer masuk Rp 200.111']);

        // Missing headers -> 401
        $this->postJson('http://fastcargo.local/api/internal/payg/webhook', ['notification_text' => 'Rp200.111'])
            ->assertStatus(401);

        // Wrong X-PayG-Secret -> 401
        $this->postJson('http://fastcargo.local/api/internal/payg/webhook', ['notification_text' => 'Rp200.111'], [
            'X-PayG-Secret' => 'wrong-secret',
        ])->assertStatus(401);

        // Wrong X-PayG-Signature -> 403
        $this->call('POST', 'http://fastcargo.local/api/internal/payg/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYG_SIGNATURE' => 'sha256=deadbeef',
        ], $payload)->assertStatus(403);

        // Valid X-PayG-Secret -> 200 and settled
        $okSecret = $this->postJson('http://fastcargo.local/api/internal/payg/webhook', [
            'notification_text' => 'Transfer masuk Rp 200.111',
            'app_package' => 'com.gojek.gobiz',
            'title' => 'GoBiz',
        ], [
            'X-PayG-Secret' => 'macrodroid-super-secret-777',
        ]);

        $okSecret->assertStatus(200)
            ->assertJson([
                'success' => true,
                'transaction_ref' => $tx1->transaction_ref,
                'settlement_status' => 'PAID_SETTLED',
            ]);
    }

    public function test_macrodroid_webhook_parses_all_indonesian_idr_formats_and_settles_exact_match(): void
    {
        AdmSystemSetting::set('payg_secret', 'macro-format-secret');

        $cases = [
            ['amount' => 150342, 'payload' => ['notification_text' => 'Kamu menerima pembayaran QRIS sebesar Rp150.342', 'app_package' => 'com.gojek.gobiz', 'title' => 'GoBiz']],
            ['amount' => 150343, 'payload' => ['text' => 'Pembayaran QRIS berhasil diterima Rp 150.343,00']],
            ['amount' => 1250342, 'payload' => ['message' => 'Transfer masuk IDR 1.250.342']],
            ['amount' => 50001, 'payload' => ['body' => 'Ada dana masuk Rp50,001']],
            ['amount' => 50002, 'payload' => ['notification_text' => 'Dana masuk Rp50.002']],
        ];

        foreach ($cases as $index => $case) {
            $invoice = $this->createUnpaidInvoice($case['amount'] - 100);
            $tx = PaymentTransaction::create([
                'invoice_id' => $invoice->id,
                'payer_user_id' => $this->customer->id,
                'transaction_ref' => 'SGPAY-FMT-'.$index,
                'payment_gateway' => 'SGPAY',
                'payment_channel' => 'QRIS',
                'amount' => $case['amount'],
                'payment_status' => PaymentTransaction::STATUS_PENDING,
            ]);

            $resp = $this->postJson(
                'http://fastcargo.local/api/internal/payg/webhook',
                $case['payload'],
                ['X-PayG-Secret' => 'macro-format-secret']
            );

            $resp->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'transaction_ref' => $tx->transaction_ref,
                    'invoice_id' => $invoice->id,
                    'matched_amount' => $case['amount'],
                    'settlement_status' => 'PAID_SETTLED',
                ]);

            $tx->refresh();
            $invoice->refresh();
            $this->assertEquals(PaymentTransaction::STATUS_SETTLEMENT, $tx->payment_status);
            $this->assertEquals(AdmInvoice::STATUS_PAID_SETTLED, $invoice->payment_status);
            $this->assertEquals('MACRODROID_NOTIFICATION', $tx->raw_gateway_payload['source'] ?? null);

            $escrow = AdmEscrowLedger::where('cargo_booking_id', $invoice->cargo_booking_id)->first();
            $this->assertNotNull($escrow);

            $this->assertDatabaseHas('adm_immutable_audit_trails', [
                'action' => 'PAYG_WEBHOOK_AUTO_MATCHED',
                'target_id' => $tx->id,
            ]);
        }
    }

    public function test_macrodroid_webhook_rejects_negative_zero_unparsable_stale_and_ambiguous_matches(): void
    {
        AdmSystemSetting::set('payg_secret', 'macro-edge-secret');

        // 1. Empty payload preserves 400
        $this->postJson('http://fastcargo.local/api/internal/payg/webhook', [], [
            'X-PayG-Secret' => 'macro-edge-secret',
        ])->assertStatus(400)
            ->assertJson(['message' => 'Missing transaction_ref parameter.']);

        // 2. Negative, zero, or unparsable payloads -> 422
        foreach ([
            ['notification_text' => 'Refund -Rp150.342'],
            ['notification_text' => 'Transfer Rp -50.000'],
            ['notification_text' => 'Nominal Rp 0'],
            ['notification_text' => 'Notifikasi promo tanpa nominal'],
            ['amount' => -25000],
            ['amount' => 0],
        ] as $invalidPayload) {
            $this->postJson('http://fastcargo.local/api/internal/payg/webhook', $invalidPayload, [
                'X-PayG-Secret' => 'macro-edge-secret',
            ])->assertStatus(422);
        }

        // 3. Stale pending transaction (>24h ago) -> 404
        $staleInvoice = $this->createUnpaidInvoice(170000);
        $staleTx = PaymentTransaction::create([
            'invoice_id' => $staleInvoice->id,
            'payer_user_id' => $this->customer->id,
            'transaction_ref' => 'SGPAY-STALE-25H',
            'payment_gateway' => 'SGPAY',
            'payment_channel' => 'QRIS',
            'amount' => 170555,
            'payment_status' => PaymentTransaction::STATUS_PENDING,
        ]);
        PaymentTransaction::where('id', $staleTx->id)->update(['created_at' => now()->subHours(25)]);

        $this->postJson('http://fastcargo.local/api/internal/payg/webhook', [
            'notification_text' => 'Pembayaran QRIS masuk Rp 170.555',
        ], ['X-PayG-Secret' => 'macro-edge-secret'])->assertStatus(404);

        $this->assertEquals(AdmInvoice::STATUS_UNPAID, $staleInvoice->fresh()->payment_status);
        $this->assertDatabaseHas('adm_immutable_audit_trails', [
            'action' => 'PAYG_WEBHOOK_UNMATCHED',
        ]);

        // 4. Ambiguous match across two DIFFERENT unpaid invoices -> 409 Conflict
        $invA = $this->createUnpaidInvoice(250000);
        $invB = $this->createUnpaidInvoice(250000);

        PaymentTransaction::create([
            'invoice_id' => $invA->id,
            'payer_user_id' => $this->customer->id,
            'transaction_ref' => 'SGPAY-AMB-A',
            'payment_gateway' => 'SGPAY',
            'payment_channel' => 'QRIS',
            'amount' => 250333,
            'payment_status' => PaymentTransaction::STATUS_PENDING,
        ]);
        PaymentTransaction::create([
            'invoice_id' => $invB->id,
            'payer_user_id' => $this->customer->id,
            'transaction_ref' => 'SGPAY-AMB-B',
            'payment_gateway' => 'SGPAY',
            'payment_channel' => 'QRIS',
            'amount' => 250333,
            'payment_status' => PaymentTransaction::STATUS_PENDING,
        ]);

        $this->postJson('http://fastcargo.local/api/internal/payg/webhook', [
            'notification_text' => 'Pembayaran QRIS masuk Rp250.333',
        ], ['X-PayG-Secret' => 'macro-edge-secret'])
            ->assertStatus(409)
            ->assertJson(['candidate_count' => 2]);

        $this->assertEquals(AdmInvoice::STATUS_UNPAID, $invA->fresh()->payment_status);
        $this->assertEquals(AdmInvoice::STATUS_UNPAID, $invB->fresh()->payment_status);
        $this->assertDatabaseHas('adm_immutable_audit_trails', [
            'action' => 'PAYG_WEBHOOK_AMBIGUOUS_MATCH',
        ]);

        // 5. Multiple pending transactions on the SAME invoice -> settles latest cleanly (200)
        $invSame = $this->createUnpaidInvoice(333000);
        $olderTx = PaymentTransaction::create([
            'invoice_id' => $invSame->id,
            'payer_user_id' => $this->customer->id,
            'transaction_ref' => 'SGPAY-SAME-OLD',
            'payment_gateway' => 'SGPAY',
            'payment_channel' => 'QRIS',
            'amount' => 333444,
            'payment_status' => PaymentTransaction::STATUS_PENDING,
        ]);
        PaymentTransaction::where('id', $olderTx->id)->update(['created_at' => now()->subMinutes(10)]);

        $latestTx = PaymentTransaction::create([
            'invoice_id' => $invSame->id,
            'payer_user_id' => $this->customer->id,
            'transaction_ref' => 'SGPAY-SAME-NEW',
            'payment_gateway' => 'SGPAY',
            'payment_channel' => 'QRIS',
            'amount' => 333444,
            'payment_status' => PaymentTransaction::STATUS_PENDING,
        ]);

        $this->postJson('http://fastcargo.local/api/internal/payg/webhook', [
            'notification_text' => 'Pembayaran QRIS masuk Rp 333.444,00',
        ], ['X-PayG-Secret' => 'macro-edge-secret'])
            ->assertStatus(200)
            ->assertJson(['transaction_ref' => $latestTx->transaction_ref]);

        $this->assertEquals(AdmInvoice::STATUS_PAID_SETTLED, $invSame->fresh()->payment_status);
    }

    public function test_customer_checkout_reuses_pending_tx_supports_regenerate_and_status_polling_with_tenant_isolation(): void
    {
        AdmSystemSetting::set('payg_sgpay_static_qr', self::VALID_GOPAY_STATIC_QRIS);
        AdmSystemSetting::set('payg_secret', 'e2e-poll-secret');

        config(['payg.service_url' => 'http://unreachable-daemon.invalid:9999']);
        config(['payg.timeout' => 1]);

        $invoice = $this->createUnpaidInvoice(400000);

        // First page visit creates pending SGpay checkout with real merchant metadata
        $visit1 = $this->actingAs($this->customer, 'customer')
            ->withSession(['two_factor_verified' => true])
            ->get("http://customer.fastcargo.local/invoices/{$invoice->id}");

        $visit1->assertStatus(200);
        $visit1->assertSee('TOKO MAJU JAYA');
        $visit1->assertSee('Total Bayar Tepat');
        $visit1->assertSee('Copy QRIS');
        $visit1->assertDontSee('SociaBuzz');

        $firstTx = PaymentTransaction::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($firstTx);
        $this->assertEquals(1, PaymentTransaction::where('invoice_id', $invoice->id)->count());

        // Second page visit reuses the exact same transaction & unique amount
        $visit2 = $this->actingAs($this->customer, 'customer')
            ->withSession(['two_factor_verified' => true])
            ->get("http://customer.fastcargo.local/invoices/{$invoice->id}");

        $visit2->assertStatus(200);
        $visit2->assertSee($firstTx->transaction_ref);
        $this->assertEquals(1, PaymentTransaction::where('invoice_id', $invoice->id)->count());

        // Regenerate QR expires first tx and issues a fresh one
        $regen = $this->actingAs($this->customer, 'customer')
            ->withSession(['two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post("http://customer.fastcargo.local/invoices/{$invoice->id}/pay", [
                'action' => 'regenerate',
            ]);

        $regen->assertRedirect("http://customer.fastcargo.local/invoices/{$invoice->id}");
        $this->assertEquals(PaymentTransaction::STATUS_EXPIRED, $firstTx->fresh()->payment_status);

        $activeTx = PaymentTransaction::where('invoice_id', $invoice->id)
            ->where('payment_status', PaymentTransaction::STATUS_PENDING)
            ->first();
        $this->assertNotNull($activeTx);
        $this->assertNotEquals($firstTx->id, $activeTx->id);

        // Status polling returns is_paid: false and canonical keys
        $this->actingAs($this->customer, 'customer')
            ->withSession(['two_factor_verified' => true])
            ->getJson("http://customer.fastcargo.local/invoices/{$invoice->id}/status")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'invoice_id' => $invoice->id,
                'payment_status' => AdmInvoice::STATUS_UNPAID,
                'is_paid' => false,
                'transaction_ref' => $activeTx->transaction_ref,
                'amount' => (float) $activeTx->amount,
            ]);

        // Unrelated customer cannot poll another customer's invoice -> 404
        $otherCustomer = User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Intruder Corp',
            'username' => 'intruder_'.Str::random(5),
            'email' => 'intruder_'.Str::random(5).'@fastcargo.test',
            'password' => Hash::make('Secret123!'),
            'user_type' => 'CORPORATE_USER',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($otherCustomer, 'customer')
            ->withSession(['two_factor_verified' => true])
            ->getJson("http://customer.fastcargo.local/invoices/{$invoice->id}/status")
            ->assertStatus(404);

        // Failed webhook without transaction_ref must NOT trigger notification auto-match settlement
        $exactAmount = (int) round((float) $activeTx->amount);
        $formattedAmount = number_format($exactAmount, 0, ',', '.');

        $this->postJson('http://fastcargo.local/api/internal/payg/webhook', [
            'status' => 'FAILED',
            'notification_text' => "Pembayaran gagal Rp{$formattedAmount}",
            'app_package' => 'com.gojek.gobiz',
        ], ['X-PayG-Secret' => 'e2e-poll-secret'])->assertStatus(400);

        // Explicit negative amount alongside notification_text must be rejected with 422
        $this->postJson('http://fastcargo.local/api/internal/payg/webhook', [
            'amount' => -50000,
            'notification_text' => "Kamu menerima pembayaran QRIS sebesar Rp{$formattedAmount}",
            'app_package' => 'com.gojek.gobiz',
        ], ['X-PayG-Secret' => 'e2e-poll-secret'])->assertStatus(422);

        $this->assertEquals(AdmInvoice::STATUS_UNPAID, $invoice->fresh()->payment_status);

        // Simulate MacroDroid notification with Android non-breaking space U+00A0 hitting webhook
        $this->postJson('http://fastcargo.local/api/internal/payg/webhook', [
            'notification_text' => "Kamu menerima pembayaran QRIS sebesar Rp\u{00A0}{$formattedAmount},00",
            'app_package' => 'com.gojek.gobiz',
            'title' => 'GoBiz',
        ], ['X-PayG-Secret' => 'e2e-poll-secret'])->assertStatus(200);

        // Status polling immediately reflects paid state
        $this->actingAs($this->customer, 'customer')
            ->withSession(['two_factor_verified' => true])
            ->getJson("http://customer.fastcargo.local/invoices/{$invoice->id}/status")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'invoice_id' => $invoice->id,
                'payment_status' => AdmInvoice::STATUS_PAID_SETTLED,
                'is_paid' => true,
                'transaction_ref' => $activeTx->transaction_ref,
            ]);
    }

    public function test_extract_idr_amount_handles_nbsp_suffix_and_spaced_refund_negatives(): void
    {
        $this->assertSame(150342, PayGPaymentGatewayService::extractIdrAmount("Pembayaran masuk Rp\u{00A0}150.342,00"));
        $this->assertSame(150342, PayGPaymentGatewayService::extractIdrAmount('150.342 IDR masuk'));
        $this->assertSame(275000, PayGPaymentGatewayService::extractIdrAmount('275.000,00 IDR berhasil diterima'));
        $this->assertNull(PayGPaymentGatewayService::extractIdrAmount('Refund - Rp150.342'));
        $this->assertNull(PayGPaymentGatewayService::extractIdrAmount('- Rp 50.000'));
        $this->assertNull(PayGPaymentGatewayService::extractIdrAmount('-150.342 IDR'));
        $this->assertNull(PayGPaymentGatewayService::extractIdrAmount('Saldo keluar Rp 0,00'));
    }

    public function test_admin_validation_error_shows_submitted_qr_status_instead_of_stale_db_merchant(): void
    {
        AdmSystemSetting::set('payg_sgpay_static_qr', self::VALID_GOPAY_STATIC_QRIS, 'payment');

        $invalidQr = '0002010102115910BADMERCHANT63040000';

        $resp = $this->actingAs($this->admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->from('http://admin.fastcargo.local/settings')
            ->followingRedirects()
            ->put('http://admin.fastcargo.local/settings', [
                'payg_active_mode' => 'sgpay',
                'payg_sgpay_static_qr' => $invalidQr,
            ]);

        $resp->assertStatus(200);
        $resp->assertSee('CRC-16 Invalid', false);
        $resp->assertDontSee('TOKO MAJU JAYA', false);
    }

    public function test_standalone_fallback_avoids_colliding_pending_amount(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        $existingInvoice = $this->createUnpaidInvoice(150000);
        $now = now();
        $rows = [];
        for ($offset = 1; $offset <= 999; $offset++) {
            if ($offset === 777) {
                continue;
            }
            $rows[] = [
                'id' => Str::uuid()->toString(),
                'invoice_id' => $existingInvoice->id,
                'payer_user_id' => $this->customer->id,
                'transaction_ref' => 'SGPAY-TAKEN-'.sprintf('%03d', $offset),
                'payment_gateway' => 'SGPAY',
                'payment_channel' => 'QRIS',
                'amount' => 150000 + $offset,
                'payment_status' => PaymentTransaction::STATUS_PENDING,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        PaymentTransaction::insert($rows);

        $newInvoice = $this->createUnpaidInvoice(150000);
        $payg = app(PayGPaymentGatewayService::class);
        $checkout = $payg->generateCheckout($newInvoice, $this->customer);

        $this->assertSame(777, $checkout['unique_code']);
        $this->assertSame(150777.0, (float) $checkout['payable_amount']);
    }
}
