<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\AdmEscrowLedger;
use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FinanceController extends Controller
{
    /**
     * Financial cockpit & turnover metrics.
     */
    public function index(Request $request)
    {
        $totalPaid = AdmInvoice::where('payment_status', 'PAID_SETTLED')->sum('total_invoice_amount');
        $totalUnpaid = AdmInvoice::where('payment_status', 'UNPAID')->sum('total_invoice_amount');
        $totalEscrow = AdmEscrowLedger::sum('total_paid_amount');

        $platformCut = $totalPaid * 0.12; // 12% platform revenue fee
        $operatorPool = $totalPaid * 0.73; // 73% operator haulage share
        $portDues = $totalPaid * 0.15; // 15% port customs & terminal dues

        $recentTransactions = PaymentTransaction::with(['invoice.company'])
            ->latest()
            ->take(10)
            ->get();

        return view('admin.finance.index', [
            'role' => 'admin',
            'totalPaid' => $totalPaid,
            'totalUnpaid' => $totalUnpaid,
            'totalEscrow' => $totalEscrow,
            'platformCut' => $platformCut,
            'operatorPool' => $operatorPool,
            'portDues' => $portDues,
            'recentTransactions' => $recentTransactions,
        ]);
    }

    /**
     * Escrow ledger monitoring.
     */
    public function escrow(Request $request)
    {
        $escrows = AdmEscrowLedger::with(['cargoBooking.route', 'cargoBooking.shipper'])
            ->latest()
            ->paginate(15);

        return view('admin.finance.escrow', [
            'role' => 'admin',
            'escrows' => $escrows,
        ]);
    }

    /**
     * Manually release an escrow milestone.
     */
    public function releaseEscrow(Request $request, string $id)
    {
        $escrow = AdmEscrowLedger::findOrFail($id);
        $milestone = $request->input('milestone', 1);

        if ($milestone == 1) {
            $escrow->update(['milestone_1_released_at' => now(), 'escrow_status' => 'PARTIALLY_RELEASED']);
        } elseif ($milestone == 2) {
            $escrow->update(['milestone_2_released_at' => now(), 'escrow_status' => 'PARTIALLY_RELEASED']);
        } elseif ($milestone == 3) {
            $escrow->update(['milestone_3_released_at' => now(), 'escrow_status' => 'FULLY_SETTLED']);
        }

        return back()->with('success', "Escrow Milestone {$milestone} released successfully.");
    }
}
