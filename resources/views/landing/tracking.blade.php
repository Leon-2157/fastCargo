@extends('layouts.landing')

@section('title', 'Shipment Tracking #' . $booking->tracking_number . ' — FastCargo')
@section('body-class', 'page-dark')
@section('header-class', 'site-header--solid')

@section('content')
<div class="container py-5" style="padding-top: 120px !important; min-height: 70vh;">
  <div class="row justify-content-center">
    <div class="col-lg-10">
      <div class="card border-secondary border-opacity-50 text-white p-4 mb-4" style="background: #131c31;">
        <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom border-secondary border-opacity-25 pb-3 mb-4">
          <div>
            <span class="text-white-50 small">WAYBILL / TRACKING NUMBER</span>
            <h2 class="mb-0 text-primary font-monospace">{{ $booking->tracking_number }}</h2>
          </div>
          <div class="text-end">
            <span class="badge @if($booking->cargo_status === 'DELIVERED') bg-success @elseif($booking->cargo_status === 'SAILING') bg-info @elseif($booking->cargo_status === 'BOOKED') bg-primary @else bg-warning @endif fs-6 px-3 py-2">
              {{ $booking->cargo_status }}
            </span>
          </div>
        </div>

        <div class="row g-4 mb-4">
          <div class="col-md-6">
            <div class="p-3 bg-black bg-opacity-40 rounded border border-secondary border-opacity-25">
              <span class="text-light opacity-75 small d-block">ORIGIN PORT</span>
              <h5 class="text-white mb-0">{{ $booking->route->originPort->name ?? $booking->route->origin_port_id }}</h5>
              <small class="text-info">{{ $booking->route->originPort->city ?? '' }}, {{ $booking->route->originPort->country_code ?? '' }}</small>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 bg-black bg-opacity-40 rounded border border-secondary border-opacity-25">
              <span class="text-light opacity-75 small d-block">DESTINATION PORT</span>
              <h5 class="text-white mb-0">{{ $booking->route->destinationPort->name ?? $booking->route->destination_port_id }}</h5>
              <small class="text-info">{{ $booking->route->destinationPort->city ?? '' }}, {{ $booking->route->destinationPort->country_code ?? '' }}</small>
            </div>
          </div>
        </div>

        <h5 class="text-white border-bottom border-secondary border-opacity-25 pb-2 mb-3">Milestone Progression</h5>
        <div class="position-relative ps-4 border-start border-primary border-2 ms-2 my-4" role="list">
          @forelse($booking->trackingEvents as $ev)
            <div class="mb-4 position-relative" role="listitem">
              <span class="position-absolute translate-middle-x rounded-circle bg-primary" style="left: -22px; width: 14px; height: 14px; top: 4px;"></span>
              <div class="d-flex justify-content-between">
                <strong>{{ $ev->event_name }}</strong>
                <small class="text-white-50">{{ $ev->event_timestamp ? \Carbon\Carbon::parse($ev->event_timestamp)->format('d M Y, H:i') : '' }}</small>
              </div>
              <div class="small text-info">Status: {{ $ev->current_status }}</div>
              <div class="small text-white-50">GPS: {{ $ev->latitude }}, {{ $ev->longitude }}</div>
            </div>
          @empty
            <div class="text-white-50 small">Booking logged. Awaiting dispatch and initial container gate-in at hub terminal.</div>
          @endforelse
        </div>

        <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 text-white small d-flex justify-content-between align-items-center">
          <span>Sign in to your customer account for full commercial invoices, packing lists, and real-time cargo temperature telemetry.</span>
          <a href="{{ config('app.portal_urls.customer', 'http://customer.fastcargo.local:8080') }}/login" class="btn btn-sm btn-outline-info">Customer Portal</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

