<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\AdmEscrowLedger;
use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\PaymentTransaction;
use App\Modules\Shared\Models\Port;
use App\Modules\Shared\Models\Route;
use App\Modules\Shared\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PaymentSimulatorController extends Controller
{
    /**
     * Display the payment webhook testbed simulator console.
     */
    public function index(Request $request): View
    {
        $transactions = PaymentTransaction::with(['invoice.booking'])
            ->whereIn('payment_status', ['PENDING', 'EXPIRED', 'FAILED', 'CANCELLED', 'SETTLEMENT'])
            ->latest('created_at')
            ->take(15)
            ->get();

        $recentLogs = DB::table('adm_immutable_audit_trails')
            ->whereIn('action', ['WEBHOOK_SIMULATOR_DISPATCHED', 'SIMULATOR_SANDBOX_TX_CREATED'])
            ->latest('created_at')
            ->take(10)
            ->get();

        return view('admin.finance.webhook-simulator', [
            'role' => 'admin',
            'transactions' => $transactions,
            'recentLogs' => $recentLogs,
        ]);
    }

    /**
     * 1-Click Sandbox Test Transaction Generator.
     * Creates an isolated pending invoice and payment transaction for instant testing.
     */
    public function generateSandbox(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();

        return DB::transaction(function () use ($admin, $request) {
            $user = User::firstOrCreate(
                ['email' => 'sandbox.merchant@fastcargo.test'],
                [
                    'name' => 'Sandbox ASEAN Merchant',
                    'password' => Hash::make('Sandbox123!'),
                    'user_type' => 'CORPORATE_USER',
                    'status' => 'ACTIVE',
                    'email_verified_at' => now(),
                ]
            );

            $company = Company::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'company_name' => 'FastCargo Sandbox Enterprise Ltd',
                    'email' => $user->email,
                ]
            );

            $origin = Port::firstOrCreate(
                ['id' => 'IDJKT'],
                ['name' => 'Tanjung Priok', 'city' => 'Jakarta', 'country_code' => 'ID', 'port_type' => 'SEAPORT', 'latitude' => -6.102, 'longitude' => 106.885]
            );

            $dest = Port::firstOrCreate(
                ['id' => 'MYPKG'],
                ['name' => 'Port Klang', 'city' => 'Klang', 'country_code' => 'MY', 'port_type' => 'SEAPORT', 'latitude' => 3.002, 'longitude' => 101.398]
            );

            $route = Route::firstOrCreate(
                ['origin_port_id' => $origin->id, 'destination_port_id' => $dest->id],
                ['id' => Str::uuid()->toString(), 'transport_mode' => 'SEA', 'transit_days' => 2, 'base_rate_idr' => 5000000, 'is_active' => true]
            );

            $randomSuffix = strtoupper(Str::random(6));
            $trackingNo = "FC-SBX-{$randomSuffix}";

            $booking = OpsCargoBooking::create([
                'id' => Str::uuid()->toString(),
                'tracking_number' => $trackingNo,
                'shipper_id' => $user->id,
                'company_id' => $company->id,
                'route_id' => $route->id,
                'cargo_status' => 'GATE_IN',
                'total_weight_kg' => 250,
                'total_volume_cbm' => 1.5,
                'declared_goods_value' => 25000000,
                'currency' => 'IDR',
            ]);

            $amount = 6500000.00;
            $invoice = AdmInvoice::create([
                'id' => Str::uuid()->toString(),
                'company_id' => $company->id,
                'cargo_booking_id' => $booking->id,
                'invoice_number' => "SANDBOX-INV-{$randomSuffix}",
                'total_invoice_amount' => $amount,
                'currency' => 'IDR',
                'due_date' => now()->addDays(3),
                'payment_status' => 'PENDING',
            ]);

            $txRef = "SANDBOX-DANA-{$randomSuffix}";
            $transaction = PaymentTransaction::create([
                'id' => Str::uuid()->toString(),
                'invoice_id' => $invoice->id,
                'payer_user_id' => $user->id,
                'payment_gateway' => 'DANA_BUSINESS',
                'payment_channel' => 'QRIS_SNAP',
                'transaction_ref' => $txRef,
                'amount' => $amount,
                'payment_status' => 'PENDING',
                'raw_gateway_payload' => [
                    'mode' => 'SANDBOX_SIMULATOR',
                    'created_by_admin' => $admin?->name ?? 'Admin',
                ],
            ]);

            DB::table('adm_immutable_audit_trails')->insert([
                'actor_id' => $admin?->id,
                'actor_type' => 'ADMIN',
                'action' => 'SIMULATOR_SANDBOX_TX_CREATED',
                'target_type' => 'payment_transactions',
                'target_id' => $transaction->id,
                'before' => json_encode(['invoice_number' => $invoice->invoice_number]),
                'after' => json_encode(['transaction_ref' => $txRef, 'amount' => $amount]),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sandbox test transaction generated successfully.',
                'transaction' => [
                    'id' => $transaction->id,
                    'transaction_ref' => $transaction->transaction_ref,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $amount,
                    'currency' => 'IDR',
                    'status' => 'PENDING',
                    'customer' => $user->name,
                ],
            ]);
        });
    }

    /**
     * Dispatch simulated webhook callback to the official /api/webhooks/dana handler.
     */
    public function fireWebhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partnerReferenceNo' => ['required', 'string'],
            'scenario' => ['required', 'string', 'in:SETTLEMENT,EXPIRED,FAILED,CANCELLED,00'],
            'amount' => ['nullable', 'numeric'],
        ]);

        $ref = $validated['partnerReferenceNo'];
        $scenario = strtoupper($validated['scenario']);

        // Standard Dana Snap callback structure
        $payload = [
            'responseCode' => '2002500',
            'responseMessage' => 'Successful',
            'partnerReferenceNo' => $ref,
            'originalPartnerReferenceNo' => $ref,
            'transactionStatus' => $scenario,
            'latestTransactionStatus' => $scenario,
            'amount' => [
                'value' => number_format((float) ($validated['amount'] ?? 6500000.00), 2, '.', ''),
                'currency' => 'IDR',
            ],
            'paidTime' => now()->toIso8601String(),
            'additionalInfo' => [
                'channel' => 'DANA_BUSINESS_QRIS',
                'terminal_id' => 'FC-SANDBOX-TERM-01',
            ],
        ];

        // Dispatch internally to PaymentWebhookController
        $webhookRequest = Request::create(
            '/api/webhooks/dana',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            json_encode($payload)
        );

        $response = app()->handle($webhookRequest);
        $statusCode = $response->getStatusCode();
        $responseContent = json_decode($response->getContent(), true) ?: $response->getContent();

        // Refresh database state for live telemetry inspect
        $tx = PaymentTransaction::where('transaction_ref', $ref)->first();
        $invoice = $tx ? AdmInvoice::find($tx->invoice_id) : null;
        $escrow = $invoice ? AdmEscrowLedger::where('cargo_booking_id', $invoice->cargo_booking_id)->first() : null;

        // Record governance audit log
        DB::table('adm_immutable_audit_trails')->insert([
            'actor_id' => auth('admin')->id(),
            'actor_type' => 'ADMIN',
            'action' => 'WEBHOOK_SIMULATOR_DISPATCHED',
            'target_type' => 'payment_transactions',
            'target_id' => $tx?->id,
            'before' => json_encode(['scenario' => $scenario, 'ref' => $ref]),
            'after' => json_encode([
                'status_code' => $statusCode,
                'response' => $responseContent,
                'tx_status' => $tx?->payment_status,
                'invoice_status' => $invoice?->payment_status,
            ]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => $statusCode >= 200 && $statusCode < 300,
            'http_status' => $statusCode,
            'scenario' => $scenario,
            'request_payload' => $payload,
            'response_payload' => $responseContent,
            'ledger_state' => [
                'transaction_status' => $tx?->payment_status ?? 'NOT_FOUND',
                'invoice_status' => $invoice?->payment_status ?? 'NOT_FOUND',
                'escrow_status' => $escrow?->escrow_status ?? 'NOT_FUNDED',
                'escrow_amount_held' => $escrow ? number_format((float) $escrow->total_paid_amount, 2, '.', '') : '0.00',
            ],
        ]);
    }
}
