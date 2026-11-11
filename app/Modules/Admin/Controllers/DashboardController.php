<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\AdmEscrowLedger;
use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\AdmOperatorInvitation;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\User;
use App\Modules\Shared\Services\CockpitMapService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(public CockpitMapService $cockpitMapService) {}

    public function index(Request $request): View
    {
        $user = Auth::guard('admin')->user();

        $totalUsersCount = User::count();
        $pendingOperatorApplications = AdmOperatorInvitation::where('status', 'PENDING_REVIEW')->count();
        $pendingKycCount = User::where('kyc_aml_status', 'PENDING')->count();
        $totalEscrowLocked = AdmEscrowLedger::where('escrow_status', 'HELD_IN_ESCROW')->sum('total_paid_amount');
        $totalRevenueInvoiced = AdmInvoice::where('payment_status', 'PAID_SETTLED')->sum('total_invoice_amount');

        $recentBookings = OpsCargoBooking::with(['shipper', 'route.originPort', 'route.destinationPort'])
            ->latest()
            ->take(5)
            ->get();

        $mapPayload = $this->cockpitMapService->buildAdminPayload();

        $pageContext = [
            'admin' => $user->name,
            'role' => $user->admin_role ?? 'SUPER_ADMIN',
            'total_users' => $totalUsersCount,
            'pending_applications' => $pendingOperatorApplications,
            'pending_kyc' => $pendingKycCount,
            'escrow_locked_idr' => (float) $totalEscrowLocked,
            'total_settled_idr' => (float) $totalRevenueInvoiced,
            'recent_bookings' => $recentBookings->map(fn (OpsCargoBooking $b): array => [
                'tracking_number' => $b->tracking_number,
                'status' => $b->cargo_status,
                'type' => $b->booking_type,
                'corridor' => ($b->route->originPort->id ?? '').'->'.($b->route->destinationPort->id ?? ''),
            ])->all(),
        ];

        return view('admin.dashboard.index', compact(
            'user',
            'totalUsersCount',
            'pendingOperatorApplications',
            'pendingKycCount',
            'totalEscrowLocked',
            'totalRevenueInvoiced',
            'recentBookings',
            'mapPayload',
            'pageContext'
        ))->with('role', 'admin');
    }

    public function mapData(): JsonResponse
    {
        return response()->json($this->cockpitMapService->buildAdminPayload());
    }
}
