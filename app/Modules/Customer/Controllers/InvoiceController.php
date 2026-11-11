<?php

namespace App\Modules\Customer\Controllers;

use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\PaymentTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
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

        return view('customer.invoice.show', [
            'role' => 'customer',
            'invoice' => $invoice,
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
     * Simulate Dana Business QRIS payment settlement.
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

        // Record payment transaction
        $transaction = PaymentTransaction::create([
            'id' => Str::uuid()->toString(),
            'invoice_id' => $invoice->id,
            'payer_user_id' => $user->id,
            'payment_gateway' => 'DANA',
            'payment_channel' => 'QRIS',
            'transaction_ref' => 'DANA-QRIS-'.strtoupper(Str::random(10)),
            'amount' => $invoice->total_invoice_amount,
            'payment_status' => 'SETTLEMENT',
            'paid_at' => now(),
            'raw_gateway_payload' => [
                'merchant_id' => 'DANA_FASTCARGO_01',
                'payment_method' => 'QRIS_STANDARDIZED',
                'status' => 'SUCCESS_SETTLED',
                'rrn' => rand(100000000000, 999999999999),
            ],
        ]);

        // Update invoice
        $invoice->update([
            'payment_status' => 'PAID_SETTLED',
            'paid_at' => now(),
        ]);

        // Update cargo booking status if currently booked
        if ($invoice->booking && $invoice->booking->cargo_status === 'BOOKED') {
            $invoice->booking->update(['cargo_status' => 'BOOKED']);
        }

        return redirect()->route('customer.invoices.show', $invoice->id)
            ->with('success', 'Dana Business QRIS payment processed and settled successfully!');
    }
}
