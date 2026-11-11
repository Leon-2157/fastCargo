@extends('layouts.dashboard')

@section('title', 'Customer Cargo Cockpit')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <h3 class="text-dark mb-0 fw-bold">Customer Cargo Cockpit</h3>
  <div class="mt-2 mt-sm-0">
    <a class="btn btn-primary btn-sm shadow-sm cockpit-touch-btn" role="button" href="{{ route('customer.bookings.create') }}">
      <i class="fas fa-plus fa-sm text-white-50 me-1" aria-hidden="true"></i> New Cargo Booking
    </a>
  </div>
</div>

<!-- KPI Cards -->
<div class="row">
  <div class="col-md-6 col-xl-3 mb-4">
    <div class="card shadow border-start-primary py-2">
      <div class="card-body">
        <div class="row align-items-center no-gutters">
          <div class="col me-2">
            <div class="text-uppercase text-primary fw-bold text-xs mb-1"><span>Active Shipments</span></div>
            <div class="text-dark fw-bold h5 mb-0 cockpit-tabular"><span>{{ $activeBookingsCount }}</span></div>
          </div>
          <div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300" aria-hidden="true"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3 mb-4">
    <div class="card shadow border-start-success py-2">
      <div class="card-body">
        <div class="row align-items-center no-gutters">
          <div class="col me-2">
            <div class="text-uppercase text-success fw-bold text-xs mb-1"><span>Completed Delivery</span></div>
            <div class="text-dark fw-bold h5 mb-0 cockpit-tabular"><span>{{ $deliveredBookingsCount }}</span></div>
          </div>
          <div class="col-auto"><i class="fas fa-clipboard-check fa-2x text-gray-300" aria-hidden="true"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3 mb-4">
    <div class="card shadow border-start-warning py-2">
      <div class="card-body">
        <div class="row align-items-center no-gutters">
          <div class="col me-2">
            <div class="text-uppercase text-warning fw-bold text-xs mb-1"><span>Outstanding Invoices</span></div>
            <div class="text-dark fw-bold h5 mb-0 cockpit-tabular"><span>IDR {{ number_format($unpaidInvoices, 0, ',', '.') }}</span></div>
          </div>
          <div class="col-auto"><i class="fas fa-file-invoice-dollar fa-2x text-gray-300" aria-hidden="true"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3 mb-4">
    <div class="card shadow border-start-info py-2">
      <div class="card-body">
        <div class="row align-items-center no-gutters">
          <div class="col me-2">
            <div class="text-uppercase text-info fw-bold text-xs mb-1"><span>Account Tier</span></div>
            <div class="text-dark fw-bold h6 mb-0">
              <span class="badge @if($user->isCorporate()) bg-primary text-white @else bg-secondary text-white @endif">{{ $user->user_type }}</span>
            </div>
          </div>
          <div class="col-auto"><i class="fas fa-shield-alt fa-2x text-gray-300" aria-hidden="true"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Asymmetric Workspace: Left Shipment Map | Right AI Summary + Consignments Table -->
<div class="row g-4 align-items-stretch cockpit-workspace-row mb-4">
  <div class="col-12 col-xl-6 d-flex">
    <x-cockpit-map
      map-id="customerCockpitMap"
      title="My Consignment Corridor Map"
      :payload="$mapPayload"
      :refresh-url="route('customer.dashboard.map', [], false)"
    />
  </div>

  <div class="col-12 col-xl-6 d-flex flex-column gap-4">
    <div class="cockpit-ai-slot">
      @livewire('ai-insight-card', ['role' => 'customer', 'pageContext' => $pageContext])
    </div>

    <div class="card cockpit-table-card flex-grow-1 mb-0">
      <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h6 class="text-primary m-0 fw-bold">Recent Consignments &amp; Waybills</h6>
        <a href="{{ route('customer.bookings.index') }}" class="btn btn-sm btn-outline-primary cockpit-touch-btn">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Waybill &amp; Cargo</th>
                <th>Route</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($recentBookings as $b)
                @php
                  $cBadge = match ($b->cargo_status) {
                    'DELIVERED' => 'bg-success text-white',
                    'SAILING' => 'bg-info text-dark',
                    'BOOKED' => 'bg-primary text-white',
                    'CANCELLED' => 'bg-danger text-white',
                    default => 'bg-warning text-dark',
                  };
                  $targetLayer = in_array($b->cargo_status, ['DELIVERED', 'CANCELLED'], true) ? 'all' : 'active';
                @endphp
                <tr>
                  <td>
                    <div class="font-monospace fw-bold text-primary">{{ $b->tracking_number }}</div>
                    <div class="cockpit-subcopy text-break cockpit-tabular">
                      {{ $b->booking_type }} &bull; {{ $b->total_weight_kg }} kg / {{ $b->total_volume_cbm }} cbm
                    </div>
                  </td>
                  <td>
                    <div class="fw-semibold text-dark">
                      {{ $b->route->originPort->id ?? '-' }} &rarr; {{ $b->route->destinationPort->id ?? '-' }}
                    </div>
                    <div class="cockpit-subcopy">
                      {{ $b->route->originPort->city ?? '' }} to {{ $b->route->destinationPort->city ?? '' }}
                    </div>
                  </td>
                  <td>
                    <span class="badge {{ $cBadge }}">{{ $b->cargo_status }}</span>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-1">
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-primary cockpit-touch-btn"
                        data-cockpit-focus-map="customerCockpitMap"
                        data-cockpit-layer="{{ $targetLayer }}"
                        data-cockpit-item-id="{{ $b->id }}"
                        title="Locate {{ $b->tracking_number }} on map"
                        aria-label="Locate {{ $b->tracking_number }} on map"
                      >
                        <i class="fas fa-crosshairs" aria-hidden="true"></i>
                      </button>
                      <a
                        href="{{ route('customer.tracking.show', $b->id, false) }}"
                        class="btn btn-sm btn-outline-secondary cockpit-touch-btn"
                      >
                        <i class="fas fa-map-marker-alt me-1" aria-hidden="true"></i>Track
                      </a>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="text-center py-4 cockpit-subcopy">
                    No cargo shipments recorded yet. Click <strong>"New Cargo Booking"</strong> above to book your first consignment.
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

