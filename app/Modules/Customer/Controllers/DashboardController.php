<?php

namespace App\Modules\Customer\Controllers;

use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\OpsCargoBooking;
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
        $user = Auth::guard('customer')->user();

        $activeBookingsCount = OpsCargoBooking::where('shipper_id', $user->id)
            ->whereNotIn('cargo_status', ['DELIVERED', 'CANCELLED'])
            ->count();

        $deliveredBookingsCount = OpsCargoBooking::where('shipper_id', $user->id)
            ->where('cargo_status', 'DELIVERED')
            ->count();

        $recentBookings = OpsCargoBooking::where('shipper_id', $user->id)
            ->with(['route.originPort', 'route.destinationPort'])
            ->latest()
            ->take(5)
            ->get();

        $unpaidInvoices = AdmInvoice::whereHas('cargoBooking', fn ($q) => $q->where('shipper_id', $user->id))
            ->where('payment_status', 'UNPAID')
            ->sum('total_invoice_amount');

        $mapPayload = $this->cockpitMapService->buildCustomerPayload($user);

        $pageContext = [
            'active_shipments' => $activeBookingsCount,
            'completed' => $deliveredBookingsCount,
            'unpaid_idr' => (float) $unpaidInvoices,
            'customer_type' => $user->user_type,
            'kyc_status' => $user->kyc_aml_status,
            'recent_waybills' => $recentBookings->map(fn (OpsCargoBooking $b): array => [
                'tracking_number' => $b->tracking_number,
                'status' => $b->cargo_status,
                'type' => $b->booking_type,
                'corridor' => ($b->route->originPort->id ?? '').'->'.($b->route->destinationPort->id ?? ''),
            ])->all(),
        ];

        return view('customer.dashboard.index', compact(
            'user',
            'activeBookingsCount',
            'deliveredBookingsCount',
            'recentBookings',
            'unpaidInvoices',
            'mapPayload',
            'pageContext'
        ))->with('role', 'customer');
    }

    public function mapData(): JsonResponse
    {
        $user = Auth::guard('customer')->user();

        return response()->json($this->cockpitMapService->buildCustomerPayload($user));
    }
}
