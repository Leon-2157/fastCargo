@extends('layouts.dashboard')

@section('title', 'Fleet Telemetry & AIS Monitoring')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="/assets/dashboard/css/maritime-telemetry.css?v=2" />
<style>
  #fleetMap { height: 600px; width: 100%; border-radius: 8px; z-index: 1; }
</style>
@endpush

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Live Fleet AIS &amp; Corridors Map</h3>
    <p class="text-muted small mb-0">ASEAN maritime corridors, airport hubs, and cross-border land passage routes.</p>
  </div>
  <div>
    <span class="badge bg-success px-3 py-2"><i class="fas fa-satellite me-1"></i> Soketi WebSocket Live Telemetry Feed</span>
  </div>
</div>

<div class="row">
  <div class="col-lg-9 mb-4">
    <div class="card shadow">
      <div class="card-body p-0">
        <div id="fleetMap"></div>
      </div>
    </div>
  </div>

  <div class="col-lg-3 mb-4">
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-ship me-1"></i> Active Manifests</h6>
      </div>
      <div class="card-body p-0" style="max-height: 280px; overflow-y: auto;">
        @forelse($activeManifests as $m)
          <div class="p-3 border-bottom small">
            <div class="d-flex justify-content-between mb-1">
              <span class="fw-bold text-primary">{{ $m->manifest_number }}</span>
              <span class="badge bg-primary">{{ $m->status }}</span>
            </div>
            <div>{{ $m->vessel_or_vehicle_name }} ({{ $m->voyage_number }})</div>
            <div class="text-muted text-xs mt-1">{{ $m->origin_port_id }} &rarr; {{ $m->destination_port_id }}</div>
          </div>
        @empty
          <div class="p-3 text-center text-muted small">No active voyages currently sailing.</div>
        @endforelse
      </div>
    </div>

    <div class="card shadow border-start-info">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-info-circle me-1"></i> Weather Stations</h6>
      </div>
      <div class="card-body small text-muted">
        <div><strong>Jakarta Hub:</strong> 29°C &bull; Calm Sea</div>
        <div><strong>Singapore Straits:</strong> 28°C &bull; Moderate Current</div>
        <div><strong>Port Klang:</strong> 30°C &bull; Clear Passage</div>
        <div><strong>Bangkok Gulf:</strong> 31°C &bull; Light Swell</div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/assets/dashboard/js/maritime-telemetry-engine.js?v=2"></script>
<script>
  let map = L.map('fleetMap').setView([-1.0, 106.0], 5);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap | FastCargo Regional AIS',
    maxZoom: 18
  }).addTo(map);

  const manifests = @json($activeManifests);

  manifests.forEach(m => {
    if (m.origin_port && m.destination_port) {
      const origin = [parseFloat(m.origin_port.latitude), parseFloat(m.origin_port.longitude)];
      const destination = [parseFloat(m.destination_port.latitude), parseFloat(m.destination_port.longitude)];

      if (m.status === 'SAILING') {
        new FastCargoMaritimeCruise(map, {
          origin: origin,
          destination: destination,
          originHub: m.origin_port_id,
          destinationHub: m.destination_port_id,
          vesselName: m.vessel_or_vehicle_name,
          voyageNumber: m.voyage_number,
          trackingNumber: m.manifest_number,
          modality: 'SEA',
          isSailing: true
        });
      } else {
        // Draw static corridor line for non-sailing manifests
        L.polyline([origin, destination], {
          color: '#0284c7',
          weight: 2,
          dashArray: '6, 6',
          opacity: 0.6
        }).addTo(map);

        let midLat = (origin[0] + destination[0]) / 2;
        let midLng = (origin[1] + destination[1]) / 2;

        L.circleMarker([midLat, midLng], {
          radius: 7,
          fillColor: '#0ea5e9',
          color: '#fff',
          weight: 2,
          fillOpacity: 1
        }).addTo(map).bindPopup(`
          <strong>${m.vessel_or_vehicle_name}</strong><br>
          <span class="badge bg-primary">${m.voyage_number}</span><br>
          Corridor: ${m.origin_port_id} &rarr; ${m.destination_port_id}
        `);
      }
    }
  });
</script>
@endpush
