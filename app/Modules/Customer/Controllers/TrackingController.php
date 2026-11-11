<?php

namespace App\Modules\Customer\Controllers;

use App\Modules\Shared\Models\OpsCargoBooking;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class TrackingController extends Controller
{
    /**
     * Map view of all active customer cargo shipments.
     */
    public function index(Request $request)
    {
        $user = Auth::guard('customer')->user();

        $activeShipments = OpsCargoBooking::where('shipper_id', $user->id)
            ->whereIn('cargo_status', ['BOOKED', 'GATE_IN', 'CUSTOMS_INSPECTION', 'SAILING', 'WEATHER_HOLD'])
            ->with(['route.originPort', 'route.destinationPort', 'trackingEvents' => fn ($q) => $q->latest('event_timestamp')->take(1)])
            ->get();

        return view('customer.tracking.index', [
            'role' => 'customer',
            'activeShipments' => $activeShipments,
        ]);
    }

    /**
     * Detailed shipment tracking view with interactive Leaflet map & telemetry.
     */
    public function show(string $id)
    {
        $user = Auth::guard('customer')->user();

        $booking = OpsCargoBooking::where('shipper_id', $user->id)
            ->where('id', $id)
            ->with([
                'route.originPort',
                'route.destinationPort',
                'items',
                'trackingEvents' => fn ($q) => $q->orderBy('event_timestamp', 'desc'),
                'manifests',
            ])
            ->firstOrFail();

        return view('customer.tracking.show', [
            'role' => 'customer',
            'booking' => $booking,
        ]);
    }
}
