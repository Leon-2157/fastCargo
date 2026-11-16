<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\AdmEscrowLedger;
use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\PaymentTransaction;
use App\Modules\Shared\Services\AuditLogService;
use App\Modules\Shared\Services\EscrowSettlementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FinanceController extends Controller
{
    public function __construct(
        private readonly EscrowSettlementService $escrowService
    ) {}

    /**
     * Financial cockpit & turnover metrics.
     */
    public function index(Request $request): View
    {
        $totalPaid = (float) AdmInvoice::where('payment_status', 'PAID_SETTLED')->sum('total_invoice_amount');
        $totalUnpaid = (float) AdmInvoice::whereIn('payment_status', ['UNPAID', 'PENDING'])->sum('total_invoice_amount');
        $totalEscrow = (float) AdmEscrowLedger::sum('total_paid_amount');

        $platformCut = round($totalPaid * 0.12, 2); // 12% Platform Revenue Fee
        $operatorPool = round($totalPaid * 0.78, 2); // 78% Operator Haulage Share
        $portDues = round($totalPaid * 0.10, 2); // 10% Tax & Port Dues

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
    public function escrow(Request $request): View
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
     * Manually release an escrow milestone with strict sequential validation (1 -> 2 -> 3).
     */
    public function releaseEscrow(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'milestone' => ['required', 'integer', 'in:1,2,3'],
        ]);

        $escrow = AdmEscrowLedger::with('cargoBooking')->findOrFail($id);
        $milestone = (int) $validated['milestone'];

        if ($escrow->escrow_status === AdmEscrowLedger::STATUS_DISPUTE_FROZEN) {
            return back()->with('error', 'Cannot release milestone while escrow ledger is frozen under dispute.');
        }

        $before = [
            'escrow_status' => $escrow->escrow_status,
            'milestone_1_released_at' => $escrow->milestone_1_released_at?->toIso8601String(),
            'milestone_2_released_at' => $escrow->milestone_2_released_at?->toIso8601String(),
            'milestone_3_released_at' => $escrow->milestone_3_released_at?->toIso8601String(),
        ];

        if ($milestone === 1) {
            if ($escrow->milestone_1_released_at !== null) {
                return back()->with('info', 'Escrow Milestone 1 has already been released.');
            }
            $escrow->update([
                'milestone_1_released_at' => now(),
                'escrow_status' => AdmEscrowLedger::STATUS_PARTIALLY_RELEASED,
            ]);
        } elseif ($milestone === 2) {
            if ($escrow->milestone_1_released_at === null) {
                return back()->with('warning', 'Sequential Release Policy: Milestone 1 (30% Gate-In) must be released before Milestone 2 (40% Sailing).');
            }
            if ($escrow->milestone_2_released_at !== null) {
                return back()->with('info', 'Escrow Milestone 2 has already been released.');
            }
            $escrow->update([
                'milestone_2_released_at' => now(),
                'escrow_status' => AdmEscrowLedger::STATUS_PARTIALLY_RELEASED,
            ]);
        } elseif ($milestone === 3) {
            if ($escrow->milestone_1_released_at === null || $escrow->milestone_2_released_at === null) {
                return back()->with('warning', 'Sequential Release Policy: Milestones 1 and 2 must be released before Milestone 3 (30% Final Delivery).');
            }
            if ($escrow->milestone_3_released_at !== null) {
                return back()->with('info', 'Escrow Milestone 3 has already been released.');
            }
            $escrow->update([
                'milestone_3_released_at' => now(),
                'escrow_status' => AdmEscrowLedger::STATUS_FULLY_SETTLED,
            ]);

            if ($escrow->cargoBooking) {
                $this->escrowService->executeRevenueSplit($escrow->cargoBooking, (float) $escrow->total_paid_amount);
            }
        }

        $escrow->refresh();

        AuditLogService::record(
            'ESCROW_MILESTONE_RELEASED',
            'adm_escrow_ledgers',
            (string) $escrow->id,
            $before,
            [
                'milestone' => $milestone,
                'escrow_status' => $escrow->escrow_status,
                'milestone_1_released_at' => $escrow->milestone_1_released_at?->toIso8601String(),
                'milestone_2_released_at' => $escrow->milestone_2_released_at?->toIso8601String(),
                'milestone_3_released_at' => $escrow->milestone_3_released_at?->toIso8601String(),
            ],
            $request
        );

        return back()->with('success', "Escrow Milestone {$milestone} released successfully.");
    }
}
