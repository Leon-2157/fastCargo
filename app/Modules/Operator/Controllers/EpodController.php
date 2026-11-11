<?php

namespace App\Modules\Operator\Controllers;

use App\Modules\Shared\Models\AdmEscrowLedger;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsEpod;
use App\Modules\Shared\Models\OpsTrackingEvent;
use App\Modules\Shared\WhatsApp\WaNotificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class EpodController extends Controller
{
    /**
     * Show ePOD entry terminal for a consignment.
     */
    public function showForm(string $id)
    {
        $booking = OpsCargoBooking::with(['route.originPort', 'route.destinationPort', 'shipper'])
            ->findOrFail($id);

        return view('operator.epod.form', [
            'role' => 'operator',
            'booking' => $booking,
        ]);
    }

    /**
     * Store electronic proof of delivery & finalize cargo fulfillment.
     */
    public function store(Request $request, string $id)
    {
        $validated = $request->validate([
            'recipient_name' => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:50',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'signature_data' => 'nullable|string',
        ]);

        $booking = OpsCargoBooking::findOrFail($id);
        $operatorProfile = Auth::guard('operator')->user()->operatorProfile;

        // 1. Create ePOD
        OpsEpod::updateOrCreate(
            ['cargo_booking_id' => $booking->id],
            [
                'recipient_name' => $validated['recipient_name'],
                'recipient_phone' => $validated['recipient_phone'],
                'signature_image_path' => ! empty($validated['signature_data']) ? 'epod_signatures/'.$booking->tracking_number.'.png' : null,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'confirmed_at' => now(),
            ]
        );

        // 2. Mark booking as DELIVERED
        $booking->update([
            'cargo_status' => 'DELIVERED',
        ]);

        // 3. Create Delivered Tracking Event
        OpsTrackingEvent::create([
            'cargo_booking_id' => $booking->id,
            'event_name' => "Consignment Successfully Delivered & ePOD Signed by {$validated['recipient_name']}",
            'current_status' => 'DELIVERED',
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'operator_id' => $operatorProfile?->id,
            'event_timestamp' => now(),
        ]);

        // 4. Update Escrow Ledger to FULLY_SETTLED if present
        $escrow = AdmEscrowLedger::where('cargo_booking_id', $booking->id)->first();
        if ($escrow) {
            $escrow->update([
                'milestone_3_released_at' => now(),
                'escrow_status' => 'FULLY_SETTLED',
            ]);
        }

        WaNotificationService::notifyDelivered(
            $booking,
            $validated['recipient_name'],
            $validated['recipient_phone']
        );

        return redirect()->route('operator.bookings.show', $booking->id)
            ->with('success', "ePOD recorded and verified. Consignment {$booking->tracking_number} marked as DELIVERED.");
    }
}
