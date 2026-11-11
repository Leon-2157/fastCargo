<?php

namespace App\Modules\Landing\Controllers;

use App\Modules\Shared\Models\OpsCargoBooking;
use Illuminate\Routing\Controller;

class TrackingPublicController extends Controller
{
    /**
     * Tracking publik tanpa login — tampilkan status dan lokasi cargo.
     * Detail lengkap hanya tersedia setelah login sebagai customer.
     */
    public function show(string $trackingNumber)
    {
        $booking = OpsCargoBooking::where('tracking_number', $trackingNumber)
            ->with(['route.originPort', 'route.destinationPort', 'trackingEvents' => fn ($q) => $q->latest('event_timestamp')->take(5)])
            ->first();

        if (! $booking) {
            return view('landing.tracking-not-found', compact('trackingNumber'));
        }

        return view('landing.tracking', compact('booking'));
    }
}
