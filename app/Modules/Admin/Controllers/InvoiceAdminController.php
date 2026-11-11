<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class InvoiceAdminController extends Controller
{
    /**
     * Master invoice ledger.
     */
    public function index(Request $request)
    {
        $query = AdmInvoice::with(['company', 'booking.route'])->latest();

        if ($request->filled('status') && $request->status !== 'ALL') {
            $query->where('payment_status', $request->status);
        }

        $invoices = $query->paginate(15)->withQueryString();

        return view('admin.invoice.index', [
            'role' => 'admin',
            'invoices' => $invoices,
        ]);
    }

    /**
     * Display a specific invoice document.
     */
    public function show(string $id)
    {
        $invoice = AdmInvoice::with(['company', 'booking.route', 'booking.items', 'paymentTransactions'])->findOrFail($id);

        return view('admin.invoice.show', [
            'role' => 'admin',
            'invoice' => $invoice,
        ]);
    }

    /**
     * Mark an invoice as paid via administrative override.
     */
    public function markPaid(Request $request, string $id)
    {
        $invoice = AdmInvoice::findOrFail($id);

        $invoice->update([
            'payment_status' => 'PAID_SETTLED',
            'paid_at' => now(),
        ]);

        PaymentTransaction::create([
            'id' => Str::uuid()->toString(),
            'invoice_id' => $invoice->id,
            'payer_user_id' => $invoice->company?->user_id ?? auth('admin')->id(),
            'payment_gateway' => 'MANUAL_TRANSFER',
            'payment_channel' => 'BANK_WIRE_SETTLEMENT',
            'transaction_ref' => 'ADMIN-SETTLE-'.strtoupper(Str::random(8)),
            'amount' => $invoice->total_invoice_amount,
            'payment_status' => 'SETTLEMENT',
            'paid_at' => now(),
            'raw_gateway_payload' => ['authorized_by' => auth('admin')->user()->name],
        ]);

        return back()->with('success', "Invoice {$invoice->invoice_number} marked as PAID & SETTLED.");
    }
}
