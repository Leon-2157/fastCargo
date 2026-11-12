<?php

namespace App\Modules\Operator\Controllers;

use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsEpod;
use App\Modules\Shared\Models\OpsTrackingEvent;
use App\Modules\Shared\Services\EscrowSettlementService;
use App\Modules\Shared\WhatsApp\WaNotificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EpodController extends Controller
{
    public function __construct(
        protected EscrowSettlementService $escrowService
    ) {}

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

        // Simpan tanda tangan digital PNG jika tersedia
        $signaturePath = null;
        if (! empty($validated['signature_data']) && str_contains($validated['signature_data'], 'data:image')) {
            $base64 = substr($validated['signature_data'], strpos($validated['signature_data'], ',') + 1);
            $decoded = base64_decode($base64);
            if ($decoded !== false) {
                $signaturePath = 'epod_signatures/'.$booking->tracking_number.'_'.time().'.png';
                Storage::disk('public')->put($signaturePath, $decoded);
            }
        }

        // 1. Create ePOD
        OpsEpod::updateOrCreate(
            ['cargo_booking_id' => $booking->id],
            [
                'recipient_name' => $validated['recipient_name'],
                'recipient_phone' => $validated['recipient_phone'],
                'signature_image_path' => $signaturePath ?? ('epod_signatures/'.$booking->tracking_number.'.png'),
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

        // 4. Update Escrow Ledger to FULLY_SETTLED & calculate revenue split (78% operator, 12% platform fee, 10% tax/port)
        $this->escrowService->checkAndReleaseMilestone($booking, 'DELIVERED');

        WaNotificationService::notifyDelivered(
            $booking,
            $validated['recipient_name'],
            $validated['recipient_phone']
        );

        return redirect()->route('operator.bookings.show', $booking->id)
            ->with('success', "ePOD recorded and verified. Consignment {$booking->tracking_number} marked as DELIVERED.");
    }
}
