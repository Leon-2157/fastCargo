@extends('layouts.dashboard')

@section('title', 'My Cargo Bookings')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">My Cargo Bookings</h3>
    <p class="text-muted small mb-0">Track, manage and inspect all your active cross-border consignments.</p>
  </div>
  <div>
    <a class="btn btn-primary btn-sm shadow-sm" href="{{ route('customer.bookings.create') }}">
      <i class="fas fa-plus fa-sm text-white-50 me-1"></i> New Cargo Booking
    </a>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-1"></i> {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<!-- Quick Metric Filters -->
<div class="row mb-4">
  <div class="col-6 col-md-3 mb-2">
    <a href="{{ route('customer.bookings.index') }}" class="card text-decoration-none shadow-sm border-start-primary {{ !request('status') || request('status') === 'ALL' ? 'bg-light' : '' }}">
      <div class="card-body py-2">
        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Bookings</div>
        <div class="h5 mb-0 fw-bold text-gray-800">{{ $counts['total'] }}</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3 mb-2">
    <a href="{{ route('customer.bookings.index', ['status' => 'BOOKED']) }}" class="card text-decoration-none shadow-sm border-start-info {{ request('status') === 'BOOKED' ? 'bg-light' : '' }}">
      <div class="card-body py-2">
        <div class="text-xs fw-bold text-info text-uppercase mb-1">Booked / Ready</div>
        <div class="h5 mb-0 fw-bold text-gray-800">{{ $counts['booked'] }}</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3 mb-2">
    <a href="{{ route('customer.bookings.index', ['status' => 'SAILING']) }}" class="card text-decoration-none shadow-sm border-start-warning {{ request('status') === 'SAILING' ? 'bg-light' : '' }}">
      <div class="card-body py-2">
        <div class="text-xs fw-bold text-warning text-uppercase mb-1">In Transit / Sailing</div>
        <div class="h5 mb-0 fw-bold text-gray-800">{{ $counts['in_transit'] }}</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3 mb-2">
    <a href="{{ route('customer.bookings.index', ['status' => 'DELIVERED']) }}" class="card text-decoration-none shadow-sm border-start-success {{ request('status') === 'DELIVERED' ? 'bg-light' : '' }}">
      <div class="card-body py-2">
        <div class="text-xs fw-bold text-success text-uppercase mb-1">Delivered</div>
        <div class="h5 mb-0 fw-bold text-gray-800">{{ $counts['delivered'] }}</div>
      </div>
    </a>
  </div>
</div>

<!-- Search & Table Card -->
<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
    <h6 class="m-0 font-weight-bold text-primary">All Registered Consignments</h6>
    <form method="GET" action="{{ route('customer.bookings.index') }}" class="d-flex" style="max-width: 320px;">
      <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm me-2" placeholder="Search tracking #...">
      <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-search"></i></button>
    </form>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Tracking Resi</th>
            <th>Corridor (Route)</th>
            <th>Type</th>
            <th>Total Load</th>
            <th>Declared Value</th>
            <th>Status</th>
            <th class="text-end pe-3">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($bookings as $booking)
            <tr>
              <td class="ps-3">
                <a href="{{ route('customer.bookings.show', $booking->id) }}" class="fw-bold text-primary text-decoration-none">
                  {{ $booking->tracking_number }}
                </a>
                <div class="small text-muted">{{ $booking->created_at->format('d M Y, H:i') }}</div>
              </td>
              <td>
                <div class="fw-semibold">
                  <span class="badge bg-secondary me-1">{{ $booking->route?->origin_port_id }}</span>
                  <i class="fas fa-arrow-right text-muted small mx-1"></i>
                  <span class="badge bg-primary">{{ $booking->route?->destination_port_id }}</span>
                </div>
                <div class="small text-muted">
                  {{ $booking->route?->originPort?->city }} to {{ $booking->route?->destinationPort?->city }} ({{ $booking->route?->transport_mode ?? 'SEA' }})
                </div>
              </td>
              <td>
                <span class="badge bg-light text-dark border">
                  {{ str_replace('_', ' ', $booking->booking_type) }}
                </span>
              </td>
              <td>
                <div class="fw-semibold">{{ number_format($booking->total_weight_kg, 1) }} kg</div>
                <div class="small text-muted">{{ number_format($booking->total_volume_cbm, 3) }} cbm</div>
              </td>
              <td>
                <div class="fw-semibold">IDR {{ number_format($booking->declared_goods_value, 0, ',', '.') }}</div>
              </td>
              <td>
                @php
                  $badgeClass = match($booking->cargo_status) {
                    'BOOKED' => 'bg-info',
                    'GATE_IN' => 'bg-secondary',
                    'CUSTOMS_INSPECTION' => 'bg-warning text-dark',
                    'SAILING' => 'bg-primary',
                    'DELIVERED' => 'bg-success',
                    'CANCELLED' => 'bg-danger',
                    default => 'bg-dark'
                  };
                @endphp
                <span class="badge {{ $badgeClass }} px-2 py-1">
                  {{ str_replace('_', ' ', $booking->cargo_status) }}
                </span>
              </td>
              <td class="text-end pe-3">
                <a href="{{ route('customer.bookings.show', $booking->id) }}" class="btn btn-sm btn-outline-primary">
                  <i class="fas fa-eye me-1"></i> Inspect
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">
                <i class="fas fa-box-open fa-3x mb-3 text-gray-300 d-block"></i>
                <p class="mb-2">No cargo bookings found for the selected filter.</p>
                <a href="{{ route('customer.bookings.create') }}" class="btn btn-primary btn-sm">
                  <i class="fas fa-plus me-1"></i> Create First Consignment
                </a>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if($bookings->hasPages())
    <div class="card-footer bg-white py-3">
      {{ $bookings->links() }}
    </div>
  @endif
</div>
@endsection

