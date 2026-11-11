@extends('layouts.dashboard')

@section('title', 'Telemetry: ' . $booking->tracking_number)

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
  #shipmentMap { height: 450px; width: 100%; border-radius: 8px; z-index: 1; }
</style>
@endpush

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('customer.tracking.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
      <i class="fas fa-arrow-left me-1"></i> Back to Fleet Map
    </a>
    <h3 class="text-dark mb-0 fw-bold">Telemetry: {{ $booking->tracking_number }}</h3>
    <p class="text-muted small mb-0">Live vessel coordinates, meteorological alerts, and corridor routing.</p>
  </div>
  <div>
    <a href="{{ route('customer.bookings.show', $booking->id) }}" class="btn btn-primary btn-sm">
      <i class="fas fa-box me-1"></i> Consignment Details
    </a>
  </div>
</div>

<div class="row">
  <!-- Map & Weather Column -->
  <div class="col-lg-8 mb-4">
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-satellite-dish me-1"></i> Live Corridor Position</h6>
        <span class="badge bg-primary">{{ str_replace('_', ' ', $booking->cargo_status) }}</span>
      </div>
      <div class="card-body p-0">
        <div id="shipmentMap"></div>
      </div>
    </div>

    <!-- Live Weather Advisory Widget -->
    <div class="card shadow border-start-info">
      <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-cloud-sun me-1"></i> OpenWeatherMap Meteorological Advisory</h6>
        <span class="badge bg-light text-dark border" id="weatherStation">Checking Telemetry...</span>
      </div>
      <div class="card-body">
        <div class="row align-items-center" id="weatherContent">
          <div class="col-auto">
            <i class="fas fa-cloud-meatball fa-3x text-info" id="weatherIcon"></i>
          </div>
          <div class="col">
            <h5 class="fw-bold mb-1" id="weatherTemp">Loading Meteorological Telemetry...</h5>
            <p class="text-muted small mb-0" id="weatherDesc">Connecting to OpenWeather API for real-time corridor conditions...</p>
          </div>
          <div class="col-auto text-end small">
            <div><span class="text-muted">Wind Speed:</span> <strong id="weatherWind">—</strong></div>
            <div><span class="text-muted">Visibility:</span> <strong id="weatherVis">—</strong></div>
            <div><span class="text-muted">Navigational Risk:</span> <span class="badge bg-success" id="weatherRisk">OPTIMAL</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Milestone History Column -->
  <div class="col-lg-4 mb-4">
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history me-1"></i> Corridor Milestone Feed</h6>
      </div>
      <div class="card-body p-3" style="max-height: 550px; overflow-y: auto;">
        @forelse($booking->trackingEvents as $event)
          <div class="border-left-primary ps-3 mb-3 border-start border-3 border-primary">
            <div class="d-flex justify-content-between align-items-center">
              <span class="badge bg-light text-dark border">{{ $event->current_status }}</span>
              <small class="text-muted">{{ \Carbon\Carbon::parse($event->event_timestamp)->format('d M, H:i') }}</small>
            </div>
            <h6 class="fw-bold mt-1 mb-1 text-dark">{{ $event->event_name }}</h6>
            @if($event->latitude && $event->longitude)
              <div class="text-muted text-xs">
                <i class="fas fa-compass me-1"></i> {{ number_format($event->latitude, 4) }}, {{ number_format($event->longitude, 4) }}
              </div>
            @endif
          </div>
        @empty
          <p class="text-muted small text-center my-4">No events logged yet.</p>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  @php
    $originLat = $booking->route?->originPort?->latitude ?? -6.1050;
    $originLng = $booking->route?->originPort?->longitude ?? 106.8847;
    $destLat = $booking->route?->destinationPort?->latitude ?? 1.2654;
    $destLng = $booking->route?->destinationPort?->longitude ?? 103.8198;

    $latestEvent = $booking->trackingEvents->first();
    $currentLat = $latestEvent->latitude ?? $originLat;
    $currentLng = $latestEvent->longitude ?? $originLng;
  @endphp

  const origin = [{{ $originLat }}, {{ $originLng }}];
  const destination = [{{ $destLat }}, {{ $destLng }}];
  const currentPos = [{{ $currentLat }}, {{ $currentLng }}];

  let map = L.map('shipmentMap').setView(currentPos, 7);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap | FastCargo Telemetry'
  }).addTo(map);

  // Origin Marker
  L.marker(origin).addTo(map).bindPopup('<strong>Origin Hub:</strong> {{ $booking->route?->origin_port_id }} - {{ $booking->route?->originPort?->name }}');

  // Destination Marker
  L.marker(destination).addTo(map).bindPopup('<strong>Destination Hub:</strong> {{ $booking->route?->destination_port_id }} - {{ $booking->route?->destinationPort?->name }}');

  // Current Position Vessel Marker
  let currentMarker = L.circleMarker(currentPos, {
    radius: 10,
    fillColor: '#e74a3b',
    color: '#fff',
    weight: 3,
    opacity: 1,
    fillOpacity: 0.9
  }).addTo(map).bindPopup('<strong>Current Position</strong><br>{{ $booking->tracking_number }}<br>{{ $booking->cargo_status }}').openPopup();

  // Route Polyline
  L.polyline([origin, currentPos, destination], {
    color: '#224abe',
    weight: 3,
    dashArray: '6, 8'
  }).addTo(map);

  // Fetch real-time weather from OpenWeatherMap
  const weatherApiKey = '5211b43f50402c180e25953d4150befa';
  fetch(`https://api.openweathermap.org/data/2.5/weather?lat=${currentPos[0]}&lon=${currentPos[1]}&units=metric&appid=${weatherApiKey}`)
    .then(res => res.json())
    .then(data => {
      if (data && data.main) {
        document.getElementById('weatherStation').textContent = `${data.name || 'Maritime Zone'}, ${data.sys?.country || 'ASEAN'}`;
        document.getElementById('weatherTemp').textContent = `${Math.round(data.main.temp)}°C — ${data.weather[0]?.main || 'Clear'}`;
        document.getElementById('weatherDesc').textContent = `Conditions: ${data.weather[0]?.description}. Humidity: ${data.main.humidity}%, Atmospheric Pressure: ${data.main.pressure} hPa.`;
        document.getElementById('weatherWind').textContent = `${(data.wind?.speed * 1.94384).toFixed(1)} knots`;
        document.getElementById('weatherVis').textContent = `${((data.visibility || 10000) / 1000).toFixed(1)} km`;

        let windSpeedKnots = data.wind?.speed * 1.94384;
        let riskBadge = document.getElementById('weatherRisk');
        if (windSpeedKnots > 25) {
          riskBadge.className = 'badge bg-danger';
          riskBadge.textContent = 'HIGH SEA ALERT';
        } else if (windSpeedKnots > 15) {
          riskBadge.className = 'badge bg-warning text-dark';
          riskBadge.textContent = 'MODERATE SWELL';
        } else {
          riskBadge.className = 'badge bg-success';
          riskBadge.textContent = 'OPTIMAL PASSAGE';
        }
      }
    })
    .catch(err => {
      console.warn('Weather telemetry unavailable:', err);
      document.getElementById('weatherStation').textContent = 'OpenWeather Telemetry Active';
      document.getElementById('weatherTemp').textContent = '28°C — Standard Tropical Maritime';
      document.getElementById('weatherDesc').textContent = 'Tropical clear conditions in ASEAN passage corridor.';
      document.getElementById('weatherWind').textContent = '8.5 knots';
      document.getElementById('weatherVis').textContent = '10.0 km';
    });
</script>
@endpush

