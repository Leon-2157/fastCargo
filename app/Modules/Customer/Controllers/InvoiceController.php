<?php

namespace App\Modules\Customer\Controllers;

use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\PaymentTransaction;
use App\Modules\Shared\Services\DanaPaymentGatewayService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    public function __construct(
        protected DanaPaymentGatewayService $danaService
    ) {}

    /**
     * Display a listing of invoices for the authenticated customer.
     */
    public function index(Request $request)
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
     * Display a specific invoice with QRIS payment modal.
     */
    public function show(string $id)
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
                'paymentTransactions' => fn ($q) => $q->latest(),
            ])
            ->firstOrFail();

        $qrisData = null;
        if ($invoice->payment_status !== 'PAID_SETTLED') {
            $qrisData = $this->danaService->generateQrisCheckout($invoice, $user);
        }

        return view('customer.invoice.show', [
            'role' => 'customer',
            'invoice' => $invoice,
            'qrisData' => $qrisData,
        ]);
    }

    /**
     * Download multi-language / English official invoice PDF.
     */
    public function downloadPdf(string $id)
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
     * Process / Simulate Dana Business QRIS payment settlement.
     */
    public function initPayment(Request $request, string $id)
    {
        $user = Auth::guard('customer')->user();
        $companyIds = Company::where('user_id', $user->id)->pluck('id');

        $invoice = AdmInvoice::whereIn('company_id', $companyIds)
            ->where('id', $id)
            ->firstOrFail();

        if ($invoice->payment_status === 'PAID_SETTLED') {
            return back()->with('info', 'This invoice has already been settled.');
        }

        // Cari transaksi pending terkini atau generate checkout baru jika belum ada
        $pendingTx = PaymentTransaction::where('invoice_id', $invoice->id)
            ->where('payment_status', 'PENDING')
            ->latest()
            ->first();

        $txRef = $pendingTx?->transaction_ref;
        if (! $txRef) {
            $checkout = $this->danaService->generateQrisCheckout($invoice, $user);
            $txRef = $checkout['transaction_ref'];
        }

        $result = $this->danaService->processSettlement($txRef, [
            'source' => 'CUSTOMER_PORTAL_SIMULATOR',
            'simulated_by_user_id' => $user->id,
            'payment_method' => 'DANA_QRIS_STANDARDIZED',
        ]);

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('customer.invoices.show', $invoice->id)
            ->with('success', 'Dana Business QRIS payment confirmed! Escrow funds locked and cargo booking updated.');
    }
}
