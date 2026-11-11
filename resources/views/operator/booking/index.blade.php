@extends('layouts.dashboard')

@section('title', 'Consignment Approvals & Operations')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Consignment Approvals &amp; Cargo Inspection</h3>
    <p class="text-muted small mb-0">Review shipper bookings, assign customs inspection channels, and dispatch freight.</p>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if(session('warning'))
  <div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-1"></i> {{ session('warning') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<!-- Quick Metric Filters -->
<div class="row mb-4">
  <div class="col-md-2 col-4 mb-2">
    <a href="{{ route('operator.bookings.index') }}" class="card text-decoration-none shadow-sm {{ !request('status') || request('status') === 'ALL' ? 'border-primary bg-light' : '' }}">
      <div class="card-body p-2 text-center">
        <div class="text-xs fw-bold text-muted text-uppercase">Total</div>
        <div class="h5 mb-0 fw-bold text-dark">{{ $bookings->total() }}</div>
      </div>
    </a>
  </div>
  <div class="col-md-2 col-4 mb-2">
    <a href="{{ route('operator.bookings.index', ['status' => 'DRAFT']) }}" class="card text-decoration-none shadow-sm {{ request('status') === 'DRAFT' ? 'border-warning bg-light' : '' }}">
      <div class="card-body p-2 text-center">
        <div class="text-xs fw-bold text-warning text-uppercase">Review (Draft)</div>
        <div class="h5 mb-0 fw-bold text-warning">{{ $stats['draft'] }}</div>
      </div>
    </a>
  </div>
  <div class="col-md-2 col-4 mb-2">
    <a href="{{ route('operator.bookings.index', ['status' => 'BOOKED']) }}" class="card text-decoration-none shadow-sm {{ request('status') === 'BOOKED' ? 'border-info bg-light' : '' }}">
      <div class="card-body p-2 text-center">
        <div class="text-xs fw-bold text-info text-uppercase">Approved</div>
        <div class="h5 mb-0 fw-bold text-info">{{ $stats['booked'] }}</div>
      </div>
    </a>
  </div>
  <div class="col-md-2 col-4 mb-2">
    <a href="{{ route('operator.bookings.index', ['status' => 'GATE_IN']) }}" class="card text-decoration-none shadow-sm {{ request('status') === 'GATE_IN' ? 'border-secondary bg-light' : '' }}">
      <div class="card-body p-2 text-center">
        <div class="text-xs fw-bold text-secondary text-uppercase">Gate-In</div>
        <div class="h5 mb-0 fw-bold text-secondary">{{ $stats['gate_in'] }}</div>
      </div>
    </a>
  </div>
  <div class="col-md-2 col-4 mb-2">
    <a href="{{ route('operator.bookings.index', ['status' => 'SAILING']) }}" class="card text-decoration-none shadow-sm {{ request('status') === 'SAILING' ? 'border-primary bg-light' : '' }}">
      <div class="card-body p-2 text-center">
        <div class="text-xs fw-bold text-primary text-uppercase">Sailing</div>
        <div class="h5 mb-0 fw-bold text-primary">{{ $stats['sailing'] }}</div>
      </div>
    </a>
  </div>
  <div class="col-md-2 col-4 mb-2">
    <a href="{{ route('operator.bookings.index', ['status' => 'DELIVERED']) }}" class="card text-decoration-none shadow-sm {{ request('status') === 'DELIVERED' ? 'border-success bg-light' : '' }}">
      <div class="card-body p-2 text-center">
        <div class="text-xs fw-bold text-success text-uppercase">Delivered</div>
        <div class="h5 mb-0 fw-bold text-success">{{ $stats['delivered'] }}</div>
      </div>
    </a>
  </div>
</div>

<!-- Bookings Table Card -->
<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
    <h6 class="m-0 font-weight-bold text-primary">All Terminal Consignments</h6>
    <form method="GET" action="{{ route('operator.bookings.index') }}" class="d-flex" style="max-width: 320px;">
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
            <th>Shipper Entity</th>
            <th>Corridor (Route)</th>
            <th>Type</th>
            <th>Gross Metric</th>
            <th>Customs Channel</th>
            <th>Status</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($bookings as $booking)
            <tr>
              <td class="ps-3">
                <a href="{{ route('operator.bookings.show', $booking->id) }}" class="fw-bold text-primary text-decoration-none">
                  {{ $booking->tracking_number }}
                </a>
                <div class="small text-muted">{{ $booking->created_at->format('d M Y, H:i') }}</div>
              </td>
              <td>
                <div class="fw-semibold text-dark">{{ $booking->shipper?->name ?? 'Shipper' }}</div>
                <div class="small text-muted">{{ $booking->company?->company_name ?? 'Individual' }}</div>
              </td>
              <td>
                <span class="badge bg-secondary">{{ $booking->route?->origin_port_id }}</span>
                <i class="fas fa-arrow-right mx-1 text-muted small"></i>
                <span class="badge bg-primary">{{ $booking->route?->destination_port_id }}</span>
                <div class="small text-muted">{{ $booking->route?->originPort?->city }} to {{ $booking->route?->destinationPort?->city }}</div>
              </td>
              <td>
                <span class="badge bg-light text-dark border">{{ str_replace('_', ' ', $booking->booking_type) }}</span>
              </td>
              <td>
                <div class="fw-bold">{{ number_format($booking->total_weight_kg, 1) }} kg</div>
                <div class="small text-muted">{{ number_format($booking->total_volume_cbm, 3) }} cbm</div>
              </td>
              <td>
                @if($booking->customs_channel === 'GREEN_CHANNEL')
                  <span class="badge bg-success">GREEN</span>
                @elseif($booking->customs_channel === 'YELLOW_CHANNEL')
                  <span class="badge bg-warning text-dark">YELLOW</span>
                @elseif($booking->customs_channel === 'RED_CHANNEL')
                  <span class="badge bg-danger">RED (INSPECT)</span>
                @else
                  <span class="badge bg-secondary">UNASSIGNED</span>
                @endif
              </td>
              <td>
                @php
                  $badgeClass = match($booking->cargo_status) {
                    'DRAFT' => 'bg-warning text-dark',
                    'BOOKED' => 'bg-info',
                    'GATE_IN' => 'bg-secondary',
                    'SAILING' => 'bg-primary',
                    'DELIVERED' => 'bg-success',
                    default => 'bg-dark'
                  };
                @endphp
                <span class="badge {{ $badgeClass }} px-2 py-1">
                  {{ str_replace('_', ' ', $booking->cargo_status) }}
                </span>
              </td>
              <td class="text-end pe-3">
                <a href="{{ route('operator.bookings.show', $booking->id) }}" class="btn btn-sm btn-outline-primary">
                  <i class="fas fa-clipboard-check me-1"></i> Review
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center py-5 text-muted">
                <i class="fas fa-box-open fa-3x mb-3 text-gray-300 d-block"></i>
                No cargo bookings found for the selected filter.
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

