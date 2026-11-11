<?php

namespace App\Modules\Operator\Controllers;

use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsManifest;
use App\Modules\Shared\Models\OpsManifestItem;
use App\Modules\Shared\Models\OpsTrackingEvent;
use App\Modules\Shared\WhatsApp\WaNotificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class BookingOpsController extends Controller
{
    /**
     * Display a listing of bookings for operator review & dispatch.
     */
    public function index(Request $request)
    {
        $query = OpsCargoBooking::with(['route.originPort', 'route.destinationPort', 'shipper', 'items'])
            ->latest();

        if ($request->filled('status') && $request->status !== 'ALL') {
            $query->where('cargo_status', $request->status);
        }

        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->where('tracking_number', 'LIKE', "%{$term}%");
        }

        $bookings = $query->paginate(12)->withQueryString();

        $stats = [
            'draft' => OpsCargoBooking::where('cargo_status', 'DRAFT')->count(),
            'booked' => OpsCargoBooking::where('cargo_status', 'BOOKED')->count(),
            'gate_in' => OpsCargoBooking::where('cargo_status', 'GATE_IN')->count(),
            'sailing' => OpsCargoBooking::where('cargo_status', 'SAILING')->count(),
            'delivered' => OpsCargoBooking::where('cargo_status', 'DELIVERED')->count(),
        ];

        return view('operator.booking.index', [
            'role' => 'operator',
            'bookings' => $bookings,
            'stats' => $stats,
        ]);
    }

    /**
     * Display a specific booking for cargo verification.
     */
    public function show(string $id)
    {
        $booking = OpsCargoBooking::with([
            'route.originPort',
            'route.destinationPort',
            'shipper',
            'company',
            'items',
            'trackingEvents' => fn ($q) => $q->orderBy('event_timestamp', 'desc'),
            'manifests',
        ])->findOrFail($id);

        $openManifests = OpsManifest::where('origin_port_id', $booking->route?->origin_port_id)
            ->where('destination_port_id', $booking->route?->destination_port_id)
            ->where('status', 'PREPARING')
            ->get();

        return view('operator.booking.show', [
            'role' => 'operator',
            'booking' => $booking,
            'openManifests' => $openManifests,
        ]);
    }

    /**
     * Approve a DRAFT booking to BOOKED.
     */
    public function approve(Request $request, string $id)
    {
        $booking = OpsCargoBooking::findOrFail($id);

        $customsChannel = $request->input('customs_channel', 'GREEN_CHANNEL');

        $booking->update([
            'cargo_status' => 'BOOKED',
            'customs_channel' => $customsChannel,
            'assigned_operator_id' => Auth::guard('operator')->user()->operatorProfile?->id,
        ]);

        OpsTrackingEvent::create([
            'cargo_booking_id' => $booking->id,
            'event_name' => "Booking Approved by Terminal Officer (Customs Channel: {$customsChannel})",
            'current_status' => 'BOOKED',
            'latitude' => $booking->route?->originPort?->latitude ?? -6.1050,
            'longitude' => $booking->route?->originPort?->longitude ?? 106.8847,
            'operator_id' => Auth::guard('operator')->user()->operatorProfile?->id,
            'event_timestamp' => now(),
        ]);

        WaNotificationService::notifyBookingStatusUpdated(
            $booking,
            "Booking Approved (Customs Channel: {$customsChannel})"
        );

        return back()->with('success', "Booking {$booking->tracking_number} approved successfully.");
    }

    /**
     * Reject a booking with operational rationale.
     */
    public function reject(Request $request, string $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $booking = OpsCargoBooking::findOrFail($id);

        $booking->update([
            'cargo_status' => 'CANCELLED',
        ]);

        OpsTrackingEvent::create([
            'cargo_booking_id' => $booking->id,
            'event_name' => 'Consignment Rejected by Hub Compliance: '.$request->reason,
            'current_status' => 'CANCELLED',
            'latitude' => $booking->route?->originPort?->latitude ?? -6.1050,
            'longitude' => $booking->route?->originPort?->longitude ?? 106.8847,
            'operator_id' => Auth::guard('operator')->user()->operatorProfile?->id,
            'event_timestamp' => now(),
        ]);

        WaNotificationService::notifyBookingStatusUpdated(
            $booking,
            'Consignment Cancelled by Hub Compliance',
            $request->reason
        );

        return back()->with('warning', "Booking {$booking->tracking_number} has been rejected.");
    }

    /**
     * Assign a booking to a manifest container.
     */
    public function assignToManifest(Request $request, string $id)
    {
        $validated = $request->validate([
            'manifest_id' => 'required|uuid|exists:ops_manifests,id',
            'container_serial_number' => 'required|string|max:20',
            'container_type' => 'required|in:DRY_20FT,DRY_40FT,REEFER_40HC',
        ]);

        $booking = OpsCargoBooking::findOrFail($id);
        $manifest = OpsManifest::findOrFail($validated['manifest_id']);

        OpsManifestItem::create([
            'manifest_id' => $manifest->id,
            'cargo_booking_id' => $booking->id,
            'container_serial_number' => $validated['container_serial_number'],
            'container_type' => $validated['container_type'],
            'seal_number' => 'PENDING_SEAL',
        ]);

        return back()->with('success', "Consignment allocated to container {$validated['container_serial_number']} on manifest {$manifest->manifest_number}.");
    }
}
