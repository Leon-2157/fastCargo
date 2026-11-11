<?php

namespace App\Modules\Operator\Controllers;

use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsIncidentEscalation;
use App\Modules\Shared\Models\OpsTrackingEvent;
use App\Modules\Shared\WhatsApp\WaNotificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class IncidentController extends Controller
{
    /**
     * Display a listing of field incident escalations.
     */
    public function index(Request $request)
    {
        $incidents = OpsIncidentEscalation::with(['cargoBooking.route', 'reporter.user'])
            ->latest()
            ->paginate(15);

        return view('operator.incident.index', [
            'role' => 'operator',
            'incidents' => $incidents,
        ]);
    }

    /**
     * Show form to file an operational incident for a specific booking.
     */
    public function create(string $bookingId)
    {
        $booking = OpsCargoBooking::with(['route.originPort', 'route.destinationPort'])->findOrFail($bookingId);

        return view('operator.incident.create', [
            'role' => 'operator',
            'booking' => $booking,
        ]);
    }

    /**
     * Store a newly created incident escalation.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cargo_booking_id' => 'required|uuid|exists:ops_cargo_bookings,id',
            'incident_type' => 'required|in:FIELD_COST,DAMAGE_CLAIM,SLA_BREACH,WEATHER_HOLD',
            'requested_amount' => 'nullable|numeric|min:0',
            'description' => 'required|string',
        ]);

        $operatorProfile = Auth::guard('operator')->user()->operatorProfile;
        $ticketNo = 'ESC-'.date('Ymd').'-'.strtoupper(Str::random(4));

        $incident = OpsIncidentEscalation::create([
            'id' => Str::uuid()->toString(),
            'incident_ticket_no' => $ticketNo,
            'cargo_booking_id' => $validated['cargo_booking_id'],
            'reporter_operator_id' => $operatorProfile->id,
            'incident_type' => $validated['incident_type'],
            'requested_amount' => $validated['requested_amount'] ?? 0,
            'approval_status' => 'PENDING_REVIEW',
            'admin_notes' => $validated['description'],
        ]);

        // If WEATHER_HOLD or DAMAGE_CLAIM, update booking status
        if (in_array($validated['incident_type'], ['WEATHER_HOLD', 'DAMAGE_CLAIM'])) {
            $booking = OpsCargoBooking::find($validated['cargo_booking_id']);
            $status = ($validated['incident_type'] === 'WEATHER_HOLD') ? 'WEATHER_HOLD' : 'CLAIM_HOLD';
            $booking->update(['cargo_status' => $status]);

            OpsTrackingEvent::create([
                'cargo_booking_id' => $booking->id,
                'event_name' => "Field Incident Escalated [Ticket: {$ticketNo}]: {$validated['incident_type']}",
                'current_status' => $status,
                'latitude' => $booking->route?->originPort?->latitude ?? -6.1050,
                'longitude' => $booking->route?->originPort?->longitude ?? 106.8847,
                'operator_id' => $operatorProfile->id,
                'event_timestamp' => now(),
            ]);

            WaNotificationService::notifyBookingStatusUpdated(
                $booking,
                "Field Hold Notice ({$validated['incident_type']})",
                "Ticket: {$ticketNo}"
            );
        }

        return redirect()->route('operator.incidents.index')
            ->with('success', "Incident ticket {$ticketNo} filed and escalated to Admin Governance.");
    }

    /**
     * Display a specific incident ticket.
     */
    public function show(string $id)
    {
        $incident = OpsIncidentEscalation::with(['cargoBooking.route', 'reporter.user'])
            ->findOrFail($id);

        return view('operator.incident.show', [
            'role' => 'operator',
            'incident' => $incident,
        ]);
    }
}
