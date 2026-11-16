<?php

namespace App\Modules\Customer\Controllers;

use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\AdmSystemSetting;
use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\PaymentTransaction;
use App\Modules\Shared\Services\PayGPaymentGatewayService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    public function __construct(
        protected PayGPaymentGatewayService $paygService
    ) {}

    /**
     * Display a listing of invoices for the authenticated customer.
     */
    public function index(Request $request): View
    {
        $user = Auth::guard('customer')->user();
        $companyIds = Company::where('user_id', $user->id)->pluck('id');

        $query = AdmInvoice::whereIn('company_id', $companyIds)
            ->with(['booking.route.originPort', 'booking.route.destinationPort'])
            ->latest();

        if ($request->filled('status') && $request->status !== 'ALL') {
            $query->where('payment_status', $request->status);
        }

        $invoices = $query->paginate(10)->withQueryString();

        $stats = [
            'total_unpaid' => AdmInvoice::whereIn('company_id', $companyIds)->where('payment_status', 'UNPAID')->sum('total_invoice_amount'),
            'total_paid' => AdmInvoice::whereIn('company_id', $companyIds)->where('payment_status', 'PAID_SETTLED')->sum('total_invoice_amount'),
            'count_unpaid' => AdmInvoice::whereIn('company_id', $companyIds)->where('payment_status', 'UNPAID')->count(),
        ];

        return view('customer.invoice.index', [
            'role' => 'customer',
            'invoices' => $invoices,
            'stats' => $stats,
        ]);
    }

    /**
     * Display a specific invoice with live SGpay Dynamic QRIS checkout.
     */
    public function show(string $id): View
    {
        $user = Auth::guard('customer')->user();
        $companyIds = Company::where('user_id', $user->id)->pluck('id');

        $invoice = AdmInvoice::whereIn('company_id', $companyIds)
            ->where('id', $id)
            ->with([
                'company',
                'booking.route.originPort',
                'booking.route.destinationPort',
                'booking.items',
                'paymentTransactions' => fn ($q) => $q->orderByDesc('created_at')->orderByDesc('id'),
            ])
            ->firstOrFail();

        $qrisData = null;
        if ($invoice->payment_status !== AdmInvoice::STATUS_PAID_SETTLED) {
            $currentStaticQr = PayGPaymentGatewayService::cleanQrisString(
                (string) AdmSystemSetting::get('payg_sgpay_static_qr', '')
            );
            $currentStaticHash = md5($currentStaticQr);

            $existingTx = $invoice->paymentTransactions
                ->where('payment_status', PaymentTransaction::STATUS_PENDING)
                ->first();

            if ($existingTx) {
                $payload = (array) ($existingTx->raw_gateway_payload ?? []);
                $storedHash = (string) ($payload['static_qr_hash'] ?? '');
                $storedQrString = (string) ($payload['qr_string'] ?? '');
                $expiresAtRaw = $payload['expires_at'] ?? null;
                $expiresAt = $expiresAtRaw ? Carbon::parse($expiresAtRaw) : null;

                if ($expiresAt !== null && $expiresAt->isPast()) {
                    $existingTx->update(['payment_status' => PaymentTransaction::STATUS_EXPIRED]);
                } elseif ($storedQrString !== ''
                    && $storedHash === $currentStaticHash
                    && $expiresAt !== null
                    && $expiresAt->isFuture()
                    && $expiresAt->gt(now()->addMinute())) {
                    $payableAmount = (float) $existingTx->amount;
                    $baseAmount = (float) $invoice->total_invoice_amount;
                    $uniqueCode = (int) ($payload['unique_code'] ?? max(0, (int) round($payableAmount - $baseAmount)));

                    $qrisData = [
                        'transaction_ref' => $existingTx->transaction_ref,
                        'amount' => $baseAmount,
                        'payable_amount' => $payableAmount,
                        'unique_code' => $uniqueCode,
                        'currency' => $invoice->currency ?? 'IDR',
                        'qr_string' => $storedQrString,
                        'qris_payload' => $storedQrString,
                        'qr_svg' => $this->paygService->renderQrSvg($storedQrString),
                        'payment_url' => (string) ($payload['payment_url'] ?? ''),
                        'expires_at' => $expiresAt,
                        'is_mock' => (bool) ($payload['is_mock'] ?? false),
                        'merchant_name' => (string) ($payload['merchant_name'] ?? 'FASTCARGO ASEAN LOGISTICS'),
                        'merchant_city' => (string) ($payload['merchant_city'] ?? 'JAKARTA'),
                        'acquirer_label' => (string) ($payload['acquirer_label'] ?? 'SGpay QRIS'),
                        'engine' => strtolower((string) ($existingTx->payment_gateway ?? 'sgpay')),
                        'channel' => (string) ($existingTx->payment_channel ?? 'QRIS'),
                    ];
                }
            }

            if ($qrisData === null) {
                $qrisData = $this->paygService->generateCheckout($invoice, $user);
            }
        }

        return view('customer.invoice.show', [
            'role' => 'customer',
            'invoice' => $invoice,
            'qrisData' => $qrisData,
        ]);
    }

    /**
     * Poll invoice settlement status for real-time customer checkout UI.
     */
    public function status(string $id): JsonResponse
    {
        $user = Auth::guard('customer')->user();
        $companyIds = Company::where('user_id', $user->id)->pluck('id');

        $invoice = AdmInvoice::whereIn('company_id', $companyIds)
            ->where('id', $id)
            ->with(['paymentTransactions' => fn ($q) => $q->orderByDesc('created_at')->orderByDesc('id')])
            ->firstOrFail();

        $transactions = $invoice->paymentTransactions;
        $latestTx = $invoice->payment_status === AdmInvoice::STATUS_PAID_SETTLED
            ? ($transactions->firstWhere('payment_status', PaymentTransaction::STATUS_SETTLEMENT) ?? $transactions->first())
            : ($transactions->firstWhere('payment_status', PaymentTransaction::STATUS_PENDING) ?? $transactions->first());

        return response()->json([
            'success' => true,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'payment_status' => $invoice->payment_status,
            'is_paid' => $invoice->payment_status === AdmInvoice::STATUS_PAID_SETTLED,
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'transaction_ref' => $latestTx?->transaction_ref,
            'amount' => $latestTx ? (float) $latestTx->amount : (float) $invoice->total_invoice_amount,
            'latest_transaction_ref' => $latestTx?->transaction_ref,
            'latest_transaction_status' => $latestTx?->payment_status,
        ]);
    }

    /**
     * Download multi-language / English official invoice PDF.
     */
    public function downloadPdf(string $id): Response
    {
        $user = Auth::guard('customer')->user();
        $companyIds = Company::where('user_id', $user->id)->pluck('id');

        $invoice = AdmInvoice::whereIn('company_id', $companyIds)
            ->where('id', $id)
            ->with([
                'company',
                'booking.route.originPort',
                'booking.route.destinationPort',
                'booking.items',
            ])
            ->firstOrFail();

        $pdf = Pdf::loadView('customer.invoice.pdf', compact('invoice'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("FastCargo-Invoice-{$invoice->invoice_number}.pdf");
    }

    /**
     * Regenerate Dynamic QRIS or (in non-production only) simulate SGpay settlement.
     */
    public function initPayment(Request $request, string $id): RedirectResponse
    {
        $user = Auth::guard('customer')->user();
        $companyIds = Company::where('user_id', $user->id)->pluck('id');

        $invoice = AdmInvoice::whereIn('company_id', $companyIds)
            ->where('id', $id)
            ->firstOrFail();

        if ($invoice->payment_status === AdmInvoice::STATUS_PAID_SETTLED) {
            return back()->with('info', 'This invoice has already been settled.');
        }

        if ($request->input('action') === 'regenerate') {
            PaymentTransaction::where('invoice_id', $invoice->id)
                ->where('payment_status', PaymentTransaction::STATUS_PENDING)
                ->update(['payment_status' => PaymentTransaction::STATUS_EXPIRED]);

            $this->paygService->generateCheckout($invoice, $user);

            return redirect()->route('customer.invoices.show', $invoice->id)
                ->with('success', 'Fresh SGpay Dynamic QRIS code generated successfully.');
        }

        if (app()->isProduction()) {
            abort(403, 'Direct payment simulation is disabled in production.');
        }

        // Non-production test simulation: settle active pending transaction or create one first
        $pendingTx = PaymentTransaction::where('invoice_id', $invoice->id)
            ->where('payment_status', PaymentTransaction::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $txRef = $pendingTx?->transaction_ref;
        if (! $txRef) {
            $checkout = $this->paygService->generateCheckout($invoice, $user);
            $txRef = $checkout['transaction_ref'];
        }

        $result = $this->paygService->processSettlement($txRef, [
            'source' => 'CUSTOMER_PORTAL_SIMULATOR',
            'simulated_by_user_id' => $user->id,
            'payment_method' => 'SGPAY_DIRECT_QRIS',
        ]);

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('customer.invoices.show', $invoice->id)
            ->with('success', 'SGpay Direct QRIS payment confirmed! Escrow funds locked and cargo booking updated.');
    }
}
