<?php

namespace App\Modules\Operator\Controllers;

use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsTrackingEvent;
use App\Modules\Shared\WhatsApp\WaNotificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class TrackingOpsController extends Controller
{
    /**
     * Display milestone dispatch console.
     */
    public function index(Request $request)
    {
        $bookings = OpsCargoBooking::with(['route.originPort', 'route.destinationPort', 'trackingEvents' => fn ($q) => $q->latest('event_timestamp')->take(1)])
            ->whereNotIn('cargo_status', ['DELIVERED', 'CANCELLED'])
            ->latest()
            ->paginate(15);

        return view('operator.tracking.index', [
            'role' => 'operator',
            'bookings' => $bookings,
        ]);
    }

    /**
     * Append an operational tracking milestone to a consignment.
     */
    public function addEvent(Request $request, string $id)
    {
        $validated = $request->validate([
            'event_name' => 'required|string|max:255',
            'current_status' => 'required|in:GATE_IN,CUSTOMS_INSPECTION,CUSTOMS_CLEARED,SAILING,ARRIVED,WEATHER_HOLD,OUT_FOR_DELIVERY',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $booking = OpsCargoBooking::findOrFail($id);
        $operatorProfile = Auth::guard('operator')->user()->operatorProfile;

        // 1. Create tracking event
        OpsTrackingEvent::create([
            'cargo_booking_id' => $booking->id,
            'event_name' => $validated['event_name'],
            'current_status' => $validated['current_status'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'operator_id' => $operatorProfile?->id,
            'event_timestamp' => now(),
        ]);

        // 2. Update booking status
        $booking->update([
            'cargo_status' => $validated['current_status'],
        ]);

        WaNotificationService::notifyBookingStatusUpdated($booking, $validated['event_name']);

        return back()->with('success', "Milestone '{$validated['event_name']}' logged for {$booking->tracking_number}.");
    }
}
