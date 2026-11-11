<?php

namespace App\Modules\Operator\Controllers;

use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsManifest;
use Illuminate\Routing\Controller;

class FleetTelemetryController extends Controller
{
    /**
     * Display live fleet AIS and vehicle telemetry map.
     */
    public function index()
    {
        $activeManifests = OpsManifest::with(['originPort', 'destinationPort', 'items'])
            ->whereIn('status', ['LOCKED_SEALED', 'SAILING', 'ARRIVED'])
            ->get();

        $activeBookings = OpsCargoBooking::with(['route.originPort', 'route.destinationPort', 'trackingEvents' => fn ($q) => $q->latest('event_timestamp')->take(1)])
            ->whereIn('cargo_status', ['GATE_IN', 'CUSTOMS_INSPECTION', 'SAILING'])
            ->get();

        return view('operator.fleet.index', [
            'role' => 'operator',
            'activeManifests' => $activeManifests,
            'activeBookings' => $activeBookings,
        ]);
    }
}
