@extends('layouts.dashboard')

@section('title', 'Operator Field Terminal')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Station Dispatch Terminal</h3>
    <small class="cockpit-subcopy">Assigned Hub: <strong>{{ $assignedHub->name ?? 'Regional Terminal' }} ({{ $profile->assigned_hub_id ?? 'IDJKT' }})</strong></small>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-2 mt-sm-0">
    <a class="btn btn-warning text-dark btn-sm shadow-sm fw-bold cockpit-touch-btn" href="{{ route('operator.manifests.create') }}">
      <i class="fas fa-plus me-1" aria-hidden="true"></i> New Voyage Manifest
    </a>
    <a class="btn btn-primary btn-sm shadow-sm cockpit-touch-btn" href="{{ route('operator.fleet.index') }}">
      <i class="fas fa-map-marked-alt me-1" aria-hidden="true"></i> Live AIS Fleet Map
    </a>
  </div>
</div>

<!-- Operator KPI Cards -->
<div class="row">
  <div class="col-md-6 col-xl-3 mb-4">
    <div class="card shadow border-start-warning py-2">
      <div class="card-body">
        <div class="row align-items-center no-gutters">
          <div class="col me-2">
            <div class="text-uppercase text-warning fw-bold text-xs mb-1"><span>Pending Bookings</span></div>
            <div class="text-dark fw-bold h5 mb-0 cockpit-tabular"><span>{{ $pendingApprovalsCount }}</span></div>
          </div>
          <div class="col-auto"><i class="fas fa-inbox fa-2x text-gray-300" aria-hidden="true"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3 mb-4">
    <div class="card shadow border-start-primary py-2">
      <div class="card-body">
        <div class="row align-items-center no-gutters">
          <div class="col me-2">
            <div class="text-uppercase text-primary fw-bold text-xs mb-1"><span>Active Manifests</span></div>
            <div class="text-dark fw-bold h5 mb-0 cockpit-tabular"><span>{{ $activeManifestsCount }}</span></div>
          </div>
          <div class="col-auto"><i class="fas fa-clipboard-list fa-2x text-gray-300" aria-hidden="true"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3 mb-4">
    <div class="card shadow border-start-danger py-2">
      <div class="card-body">
        <div class="row align-items-center no-gutters">
          <div class="col me-2">
            <div class="text-uppercase text-danger fw-bold text-xs mb-1"><span>Open Incident Tickets</span></div>
            <div class="text-dark fw-bold h5 mb-0 cockpit-tabular"><span>{{ $openIncidentsCount }}</span></div>
          </div>
          <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300" aria-hidden="true"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3 mb-4">
    <div class="card shadow border-start-success py-2">
      <div class="card-body">
        <div class="row align-items-center no-gutters">
          <div class="col me-2">
            <div class="text-uppercase text-success fw-bold text-xs mb-1"><span>Station KPI Rating</span></div>
            <div class="text-dark fw-bold h5 mb-0 cockpit-tabular"><span>{{ $profile->kpi_rating ?? '5.00' }} / 5.00</span></div>
          </div>
          <div class="col-auto"><i class="fas fa-star fa-2x text-gray-300" aria-hidden="true"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Asymmetric Workspace: Left Station Telemetry Map | Right AI Insight + Manifests Table -->
<div class="row g-4 align-items-stretch cockpit-workspace-row mb-4">
  <div class="col-12 col-xl-6 d-flex">
    <x-cockpit-map
      map-id="operatorCockpitMap"
      title="Station Dispatch & Fleet Map"
      :payload="$mapPayload"
      :refresh-url="route('operator.dashboard.map', [], false)"
    />
  </div>

  <div class="col-12 col-xl-6 d-flex flex-column gap-4">
    <div class="cockpit-ai-slot">
      @livewire('ai-insight-card', ['role' => 'operator', 'pageContext' => $pageContext])
    </div>

    <div class="card cockpit-table-card flex-grow-1 mb-0">
      <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h6 class="text-primary m-0 fw-bold">Active Station Manifests (Vessels / Transits)</h6>
        <a href="{{ route('operator.manifests.index') }}" class="btn btn-sm btn-outline-primary cockpit-touch-btn">All Manifests</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Manifest &amp; Carrier</th>
                <th>Corridor &amp; ETA</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($recentManifests as $m)
                @php
                  $mBadge = match ($m->status) {
                    'SAILING' => 'bg-info text-dark',
                    'LOCKED_SEALED' => 'bg-warning text-dark',
                    'ARRIVED' => 'bg-success text-white',
                    default => 'bg-secondary text-white',
                  };
                @endphp
                <tr>
                  <td>
                    <div class="font-monospace fw-bold text-primary">{{ $m->manifest_number }}</div>
                    <div class="cockpit-subcopy text-break">{{ $m->vessel_or_vehicle_name }} &bull; {{ $m->voyage_number }}</div>
                  </td>
                  <td class="cockpit-tabular">
                    <div class="fw-semibold text-dark">{{ $m->originPort->id ?? '-' }} &rarr; {{ $m->destinationPort->id ?? '-' }}</div>
                    <div class="cockpit-subcopy">ETA {{ \Carbon\Carbon::parse($m->departure_eta)->format('d M Y, H:i') }}</div>
                  </td>
                  <td>
                    <span class="badge {{ $mBadge }}">{{ $m->status }}</span>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-1">
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-primary cockpit-touch-btn"
                        data-cockpit-focus-map="operatorCockpitMap"
                        data-cockpit-layer="manifest"
                        data-cockpit-item-id="{{ $m->id }}"
                        title="Locate {{ $m->manifest_number }} on map"
                        aria-label="Locate {{ $m->manifest_number }} on map"
                      >
                        <i class="fas fa-crosshairs" aria-hidden="true"></i>
                      </button>
                      <a
                        href="{{ route('operator.manifests.show', $m->id, false) }}"
                        class="btn btn-sm btn-outline-secondary cockpit-touch-btn"
                      >
                        Manage
                      </a>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="text-center py-4 cockpit-subcopy">
                    No active voyage manifests in this station hub. Click <strong>"New Voyage Manifest"</strong> to prepare departure.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

