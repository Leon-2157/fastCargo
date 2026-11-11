@extends('layouts.dashboard')

@section('title', 'Live Cargo Fleet Tracking')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
  #trackingMap { height: 550px; width: 100%; border-radius: 8px; z-index: 1; }
</style>
@endpush

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Active Fleet &amp; Consignment Tracking</h3>
    <p class="text-muted small mb-0">Real-time GPS telemetry, ASEAN corridor routing, and vessel positioning.</p>
  </div>
  <div>
    <span class="badge bg-success px-3 py-2"><i class="fas fa-satellite me-1"></i> Soketi Telemetry Stream Online</span>
  </div>
</div>

<div class="row">
  <!-- Map Column -->
  <div class="col-lg-8 mb-4">
    <div class="card shadow">
      <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-globe-asia me-1"></i> ASEAN Maritime &amp; Air Corridors</h6>
        <span class="badge bg-light text-dark border">{{ $activeShipments->count() }} Active Vessels / Trucks</span>
      </div>
      <div class="card-body p-0">
        <div id="trackingMap"></div>
      </div>
    </div>
  </div>

  <!-- Active Shipments List Column -->
  <div class="col-lg-4 mb-4">
    <div class="card shadow h-100">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-boxes me-1"></i> In-Transit Consignments</h6>
      </div>
      <div class="card-body p-0" style="max-height: 550px; overflow-y: auto;">
        @forelse($activeShipments as $shipment)
          @php
            $latestEvent = $shipment->trackingEvents->first();
            $lat = $latestEvent->latitude ?? $shipment->route?->originPort?->latitude ?? -6.1050;
            $lng = $latestEvent->longitude ?? $shipment->route?->originPort?->longitude ?? 106.8847;
          @endphp
          <div class="p-3 border-bottom shipment-item" style="cursor: pointer;" onclick="focusShipment({{ $lat }}, {{ $lng }}, '{{ $shipment->tracking_number }}')">
            <div class="d-flex justify-content-between align-items-start mb-1">
              <span class="fw-bold text-primary">{{ $shipment->tracking_number }}</span>
              <span class="badge bg-primary">{{ str_replace('_', ' ', $shipment->cargo_status) }}</span>
            </div>
            <div class="small fw-semibold text-dark">
              {{ $shipment->route?->origin_port_id }} ({{ $shipment->route?->originPort?->city }}) &rarr;
              {{ $shipment->route?->destination_port_id }} ({{ $shipment->route?->destinationPort?->city }})
            </div>
            <div class="small text-muted mt-1">
              <i class="fas fa-map-marker-alt me-1 text-danger"></i>
              {{ $latestEvent->event_name ?? 'In Transit' }}
            </div>
            <div class="mt-2 text-end">
              <a href="{{ route('customer.tracking.show', $shipment->id) }}" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 11px;">
                Detailed Telemetry <i class="fas fa-arrow-right ms-1"></i>
              </a>
            </div>
          </div>
        @empty
          <div class="text-center py-5 text-muted">
            <i class="fas fa-shipping-fast fa-3x mb-3 text-gray-300 d-block"></i>
            No active shipments currently in transit.
          </div>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  let map = L.map('trackingMap').setView([-1.5, 107.0], 5);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors | FastCargo Telemetry'
  }).addTo(map);

  const shipments = @json($activeShipments);
  let markers = {};

  shipments.forEach(s => {
    let lat = (s.tracking_events && s.tracking_events.length > 0) ? s.tracking_events[0].latitude : (s.route?.origin_port?.latitude || -6.1050);
    let lng = (s.tracking_events && s.tracking_events.length > 0) ? s.tracking_events[0].longitude : (s.route?.origin_port?.longitude || 106.8847);

    let popupContent = `
      <div style="min-width: 180px;">
        <strong style="color: #224abe;">${s.tracking_number}</strong><br>
        <span class="badge bg-primary mb-1">${s.cargo_status}</span><br>
        <small>${s.route?.origin_port_id} &rarr; ${s.route?.destination_port_id}</small><br>
        <a href="/tracking/${s.id}" class="btn btn-sm btn-primary mt-2" style="font-size: 10px; color: #fff; padding: 2px 6px;">Open Telemetry</a>
      </div>
    `;

    let marker = L.marker([lat, lng]).addTo(map).bindPopup(popupContent);
    markers[s.tracking_number] = marker;

    // Draw corridor path line
    if (s.route?.origin_port && s.route?.destination_port) {
      let latlngs = [
        [s.route.origin_port.latitude, s.route.origin_port.longitude],
        [lat, lng],
        [s.route.destination_port.latitude, s.route.destination_port.longitude]
      ];
      L.polyline(latlngs, {color: '#224abe', weight: 2, dashArray: '5, 10'}).addTo(map);
    }
  });

  function focusShipment(lat, lng, trackingNumber) {
    map.setView([lat, lng], 8);
    if (markers[trackingNumber]) {
      markers[trackingNumber].openPopup();
    }
  }
</script>
@endpush

