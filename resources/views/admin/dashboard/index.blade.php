@extends('layouts.dashboard')

@section('title', 'Administrative Governance Cockpit')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Executive Governance Cockpit</h3>
    <small class="cockpit-subcopy">Master Control: <strong>FastCargo Enterprise System &bull; Role: {{ $user->admin_role ?? 'SUPER_ADMIN' }}</strong></small>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-2 mt-sm-0">
    <a class="btn btn-primary btn-sm shadow-sm cockpit-touch-btn" href="{{ route('admin.invitations.index') }}">
      <i class="fas fa-user-plus me-1" aria-hidden="true"></i> Review Applications
    </a>
    <a class="btn btn-outline-secondary btn-sm shadow-sm cockpit-touch-btn" href="{{ route('admin.settings.index') }}">
      <i class="fas fa-sliders-h me-1" aria-hidden="true"></i> System Config
    </a>
  </div>
</div>

<!-- Admin KPI Cards -->
<div class="row">
  <div class="col-md-6 col-xl-3 mb-4">
    <div class="card shadow border-start-primary py-2">
      <div class="card-body">
        <div class="row align-items-center no-gutters">
          <div class="col me-2">
            <div class="text-uppercase text-primary fw-bold text-xs mb-1"><span>Total Network Users</span></div>
            <div class="text-dark fw-bold h5 mb-0 cockpit-tabular"><span>{{ $totalUsersCount }}</span></div>
          </div>
          <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300" aria-hidden="true"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3 mb-4">
    <div class="card shadow border-start-warning py-2">
      <div class="card-body">
        <div class="row align-items-center no-gutters">
          <div class="col me-2">
            <div class="text-uppercase text-warning fw-bold text-xs mb-1"><span>Pending Operator Apps</span></div>
            <div class="text-dark fw-bold h5 mb-0 cockpit-tabular">
              <span>{{ $pendingOperatorApplications }}</span>
              @if($pendingOperatorApplications > 0)
                <span class="badge bg-danger ms-1 small">Needs Review</span>
              @endif
            </div>
          </div>
          <div class="col-auto"><i class="fas fa-id-card fa-2x text-gray-300" aria-hidden="true"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3 mb-4">
    <div class="card shadow border-start-info py-2">
      <div class="card-body">
        <div class="row align-items-center no-gutters">
          <div class="col me-2">
            <div class="text-uppercase text-info fw-bold text-xs mb-1"><span>Locked Escrow Funds</span></div>
            <div class="text-dark fw-bold h6 mb-0 cockpit-tabular"><span>IDR {{ number_format($totalEscrowLocked, 0, ',', '.') }}</span></div>
          </div>
          <div class="col-auto"><i class="fas fa-vault fa-2x text-gray-300" aria-hidden="true"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3 mb-4">
    <div class="card shadow border-start-success py-2">
      <div class="card-body">
        <div class="row align-items-center no-gutters">
          <div class="col me-2">
            <div class="text-uppercase text-success fw-bold text-xs mb-1"><span>Total Settled Revenue</span></div>
            <div class="text-dark fw-bold h6 mb-0 cockpit-tabular"><span>IDR {{ number_format($totalRevenueInvoiced, 0, ',', '.') }}</span></div>
          </div>
          <div class="col-auto"><i class="fas fa-hand-holding-usd fa-2x text-gray-300" aria-hidden="true"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Asymmetric Workspace: Left Interactive Network Maps | Right AI Briefing + Cargo Table -->
<div class="row g-4 align-items-stretch cockpit-workspace-row mb-4">
  <div class="col-12 col-xl-6 d-flex">
    <x-cockpit-map
      map-id="adminCockpitMap"
      title="Network Spatial Operations Map"
      :payload="$mapPayload"
      :refresh-url="route('admin.dashboard.map', [], false)"
    />
  </div>

  <div class="col-12 col-xl-6 d-flex flex-column gap-4">
    <div class="cockpit-ai-slot">
      @livewire('ai-insight-card', ['role' => 'admin', 'pageContext' => $pageContext])
    </div>

    <div class="card cockpit-table-card flex-grow-1 mb-0">
      <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h6 class="text-primary m-0 fw-bold">Live Network Cargo Operations (Cross-Border)</h6>
        <span class="badge bg-success text-white">AUDIT COMPLIANT</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Consignment</th>
                <th>Corridor &amp; Value</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($recentBookings as $b)
                @php
                  $isRunning = ! in_array($b->cargo_status, ['DELIVERED', 'CANCELLED'], true);
                  $badgeClass = match ($b->cargo_status) {
                    'DELIVERED' => 'bg-success text-white',
                    'SAILING' => 'bg-info text-dark',
                    'BOOKED' => 'bg-primary text-white',
                    'CANCELLED' => 'bg-danger text-white',
                    default => 'bg-warning text-dark',
                  };
                @endphp
                <tr>
                  <td>
                    <div class="font-monospace fw-bold text-primary">{{ $b->tracking_number }}</div>
                    <div class="cockpit-subcopy text-break">{{ $b->shipper->name ?? 'Shipper' }} &bull; {{ $b->booking_type }}</div>
                  </td>
                  <td class="cockpit-tabular">
                    <div class="fw-semibold text-dark">
                      {{ $b->route->originPort->id ?? '-' }} &rarr; {{ $b->route->destinationPort->id ?? '-' }}
                    </div>
                    <div class="cockpit-subcopy">IDR {{ number_format($b->declared_goods_value, 0, ',', '.') }}</div>
                  </td>
                  <td>
                    <span class="badge {{ $badgeClass }}">{{ $b->cargo_status }}</span>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-1">
                      @if($isRunning)
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-primary cockpit-touch-btn"
                          data-cockpit-focus-map="adminCockpitMap"
                          data-cockpit-layer="cargo"
                          data-cockpit-item-id="{{ $b->id }}"
                          title="Locate {{ $b->tracking_number }} on map"
                          aria-label="Locate {{ $b->tracking_number }} on map"
                        >
                          <i class="fas fa-crosshairs" aria-hidden="true"></i>
                        </button>
                      @endif
                      <a
                        href="{{ route('tracking.public', ['trackingNumber' => $b->tracking_number], false) }}"
                        class="btn btn-sm btn-outline-secondary cockpit-touch-btn"
                        title="Open public tracking for {{ $b->tracking_number }}"
                        aria-label="Open tracking for {{ $b->tracking_number }}"
                      >
                        <i class="fas fa-route" aria-hidden="true"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="text-center py-4 cockpit-subcopy">
                    No active bookings recorded in the system.
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

