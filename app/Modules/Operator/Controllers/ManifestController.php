<?php

namespace App\Modules\Operator\Controllers;

use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsManifest;
use App\Modules\Shared\Models\OpsTrackingEvent;
use App\Modules\Shared\Models\Port;
use App\Modules\Shared\WhatsApp\WaNotificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ManifestController extends Controller
{
    /**
     * Display a listing of cargo voyage manifests.
     */
    public function index(Request $request)
    {
        $query = OpsManifest::with(['originPort', 'destinationPort', 'items'])
            ->latest();

        if ($request->filled('status') && $request->status !== 'ALL') {
            $query->where('status', $request->status);
        }

        $manifests = $query->paginate(10)->withQueryString();

        $stats = [
            'preparing' => OpsManifest::where('status', 'PREPARING')->count(),
            'sealed' => OpsManifest::where('status', 'LOCKED_SEALED')->count(),
            'sailing' => OpsManifest::where('status', 'SAILING')->count(),
            'arrived' => OpsManifest::where('status', 'ARRIVED')->count(),
        ];

        return view('operator.manifest.index', [
            'role' => 'operator',
            'manifests' => $manifests,
            'stats' => $stats,
        ]);
    }

    /**
     * Show form to create a new voyage/truck manifest.
     */
    public function create()
    {
        $ports = Port::orderBy('name')->get();

        return view('operator.manifest.create', [
            'role' => 'operator',
            'ports' => $ports,
        ]);
    }

    /**
     * Store a newly created manifest in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'origin_port_id' => 'required|string|exists:ports,id',
            'destination_port_id' => 'required|string|different:origin_port_id|exists:ports,id',
            'vessel_or_vehicle_name' => 'required|string|max:255',
            'voyage_number' => 'required|string|max:50',
            'departure_eta' => 'required|date',
            'arrival_eta' => 'required|date|after:departure_eta',
        ]);

        $manifestNumber = sprintf(
            'MMK-%s-%s-%s',
            $validated['origin_port_id'],
            $validated['destination_port_id'],
            date('Ymd').'-'.strtoupper(Str::random(3))
        );

        $manifest = OpsManifest::create(array_merge($validated, [
            'id' => Str::uuid()->toString(),
            'manifest_number' => $manifestNumber,
            'status' => 'PREPARING',
        ]));

        return redirect()->route('operator.manifests.show', $manifest->id)
            ->with('success', "Voyage manifest {$manifestNumber} created. You can now allocate consignments.");
    }

    /**
     * Display a specific manifest, loaded cargo containers and seal status.
     */
    public function show(string $id)
    {
        $manifest = OpsManifest::with([
            'originPort',
            'destinationPort',
            'manifestItems.cargoBooking.route',
            'manifestItems.cargoBooking.shipper',
        ])->findOrFail($id);

        // Fetch unassigned bookings on the same route available to add
        $availableBookings = OpsCargoBooking::where('cargo_status', 'BOOKED')
            ->whereHas('route', function ($q) use ($manifest) {
                $q->where('origin_port_id', $manifest->origin_port_id)
                    ->where('destination_port_id', $manifest->destination_port_id);
            })
            ->whereDoesntHave('manifests', function ($q) use ($manifest) {
                $q->where('manifest_id', $manifest->id);
            })
            ->get();

        return view('operator.manifest.show', [
            'role' => 'operator',
            'manifest' => $manifest,
            'availableBookings' => $availableBookings,
        ]);
    }

    /**
     * Lock and affix customs security seal to manifest.
     */
    public function seal(Request $request, string $id)
    {
        $manifest = OpsManifest::with('manifestItems.cargoBooking')->findOrFail($id);

        if ($manifest->manifestItems->isEmpty()) {
            return back()->with('error', 'Cannot seal an empty manifest. Allocate at least one consignment first.');
        }

        $manifest->update([
            'status' => 'LOCKED_SEALED',
        ]);

        // Generate tamper-evident seal and update bookings
        foreach ($manifest->manifestItems as $item) {
            $sealCode = 'SEAL-'.strtoupper($manifest->origin_port_id).'-'.rand(100000, 999999);
            $item->update(['seal_number' => $sealCode]);

            if ($item->cargoBooking) {
                $item->cargoBooking->update(['cargo_status' => 'GATE_IN']);

                OpsTrackingEvent::create([
                    'cargo_booking_id' => $item->cargoBooking->id,
                    'event_name' => "Consignment Manifested & Sealed into Container {$item->container_serial_number} (Seal: {$sealCode})",
                    'current_status' => 'GATE_IN',
                    'latitude' => $manifest->originPort->latitude ?? -6.1050,
                    'longitude' => $manifest->originPort->longitude ?? 106.8847,
                    'operator_id' => Auth::guard('operator')->user()->operatorProfile?->id,
                    'event_timestamp' => now(),
                ]);

                WaNotificationService::notifyBookingStatusUpdated(
                    $item->cargoBooking,
                    "Manifested & Sealed ({$item->container_serial_number} / {$sealCode})"
                );
            }
        }

        return back()->with('success', "Manifest {$manifest->manifest_number} locked and security seals affixed.");
    }
}
