<?php

namespace App\Modules\Customer\Controllers;

use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsTrackingEvent;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    /**
     * Display a listing of the customer's cargo bookings.
     */
    public function index(Request $request)
    {
        $user = Auth::guard('customer')->user();

        $query = OpsCargoBooking::where('shipper_id', $user->id)
            ->with(['route.originPort', 'route.destinationPort', 'items'])
            ->latest();

        if ($request->filled('status') && $request->status !== 'ALL') {
            $query->where('cargo_status', $request->status);
        }

        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->where('tracking_number', 'LIKE', "%{$term}%");
        }

        $bookings = $query->paginate(10)->withQueryString();

        $counts = [
            'total' => OpsCargoBooking::where('shipper_id', $user->id)->count(),
            'booked' => OpsCargoBooking::where('shipper_id', $user->id)->where('cargo_status', 'BOOKED')->count(),
            'in_transit' => OpsCargoBooking::where('shipper_id', $user->id)->whereIn('cargo_status', ['GATE_IN', 'CUSTOMS_INSPECTION', 'SAILING'])->count(),
            'delivered' => OpsCargoBooking::where('shipper_id', $user->id)->where('cargo_status', 'DELIVERED')->count(),
        ];

        return view('customer.booking.index', [
            'role' => 'customer',
            'bookings' => $bookings,
            'counts' => $counts,
        ]);
    }

    /**
     * Show the booking wizard.
     */
    public function create()
    {
        return view('customer.booking.create', [
            'role' => 'customer',
        ]);
    }

    /**
     * Display details of a specific cargo booking.
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
                'invoices.paymentTransactions',
                'manifests.originPort',
                'manifests.destinationPort',
                'assignedOperator.user',
            ])
            ->firstOrFail();

        return view('customer.booking.show', [
            'role' => 'customer',
            'booking' => $booking,
        ]);
    }

    /**
     * Cancel an eligible cargo booking.
     */
    public function cancel(string $id)
    {
        $user = Auth::guard('customer')->user();

        $booking = OpsCargoBooking::where('shipper_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        if (! in_array($booking->cargo_status, ['DRAFT', 'BOOKED'])) {
            return back()->with('error', 'Booking cannot be cancelled once cargo processing or dispatch has begun.');
        }

        $booking->update([
            'cargo_status' => 'CANCELLED',
        ]);

        OpsTrackingEvent::create([
            'cargo_booking_id' => $booking->id,
            'event_name' => 'Consignment Cancelled by Shipper',
            'current_status' => 'CANCELLED',
            'latitude' => $booking->route?->originPort?->latitude ?? 0,
            'longitude' => $booking->route?->originPort?->longitude ?? 0,
            'event_timestamp' => now(),
        ]);

        return redirect()->route('customer.bookings.index')
            ->with('success', "Booking {$booking->tracking_number} has been cancelled successfully.");
    }
}
