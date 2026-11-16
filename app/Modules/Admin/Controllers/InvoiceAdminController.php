<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\PaymentTransaction;
use App\Modules\Shared\Services\AuditLogService;
use App\Modules\Shared\Services\EscrowSettlementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class InvoiceAdminController extends Controller
{
    public function __construct(
        private readonly EscrowSettlementService $escrowService
    ) {}

    /**
     * Master invoice ledger.
     */
    public function index(Request $request): View
    {
        $query = AdmInvoice::with(['company', 'booking.route'])->latest();

        if ($request->filled('status') && $request->status !== 'ALL') {
            if ($request->status === 'UNPAID') {
                $query->whereIn('payment_status', ['UNPAID', 'PENDING']);
            } else {
                $query->where('payment_status', $request->status);
            }
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->search);
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'LIKE', "%{$term}%")
                    ->orWhereHas('company', function ($cq) use ($term) {
                        $cq->where('company_name', 'LIKE', "%{$term}%");
                    })
                    ->orWhereHas('booking', function ($bq) use ($term) {
                        $bq->where('tracking_number', 'LIKE', "%{$term}%");
                    });
            });
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
    public function show(string $id): View
    {
        $invoice = AdmInvoice::with([
            'company.user',
            'booking.route.originPort',
            'booking.route.destinationPort',
            'booking.items',
            'booking.escrowLedger',
            'paymentTransactions' => fn ($q) => $q->latest(),
        ])->findOrFail($id);

        return view('admin.invoice.show', [
            'role' => 'admin',
            'invoice' => $invoice,
        ]);
    }

    /**
     * Mark an invoice as paid via administrative override and fund the escrow ledger.
     */
    public function markPaid(Request $request, string $id): RedirectResponse
    {
        $invoice = AdmInvoice::with(['company', 'booking'])->findOrFail($id);

        if ($invoice->payment_status === 'PAID_SETTLED') {
            return back()->with('info', "Invoice {$invoice->invoice_number} is already marked as PAID & SETTLED.");
        }

        $previousStatus = $invoice->payment_status;
        $now = now();

        $invoice->update([
            'payment_status' => 'PAID_SETTLED',
            'paid_at' => $now,
        ]);

        $admin = Auth::guard('admin')->user();
        $txRef = 'ADMIN-SETTLE-'.strtoupper(Str::random(8));

        PaymentTransaction::create([
            'id' => Str::uuid()->toString(),
            'invoice_id' => $invoice->id,
            'payer_user_id' => $invoice->company?->user_id ?? $admin?->id,
            'payment_gateway' => 'MANUAL_TRANSFER',
            'payment_channel' => 'BANK_WIRE_SETTLEMENT',
            'transaction_ref' => $txRef,
            'amount' => $invoice->total_invoice_amount,
            'payment_status' => 'SETTLEMENT',
            'paid_at' => $now,
            'raw_gateway_payload' => [
                'authorized_by' => $admin?->name ?? 'Administrator',
                'settled_at' => $now->toIso8601String(),
            ],
        ]);

        $escrow = $this->escrowService->fundEscrowFromPaidInvoice($invoice);

        AuditLogService::record(
            'INVOICE_MARKED_PAID_MANUAL',
            'adm_invoices',
            (string) $invoice->id,
            [
                'invoice_number' => $invoice->invoice_number,
                'payment_status' => $previousStatus,
            ],
            [
                'invoice_number' => $invoice->invoice_number,
                'payment_status' => 'PAID_SETTLED',
                'transaction_ref' => $txRef,
                'amount' => (float) $invoice->total_invoice_amount,
                'escrow_funded' => $escrow !== null,
            ],
            $request
        );

        return back()->with('success', "Invoice {$invoice->invoice_number} marked as PAID & SETTLED and Escrow Ledger funded.");
    }
}
