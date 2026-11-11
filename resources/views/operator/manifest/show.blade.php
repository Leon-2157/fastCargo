@extends('layouts.dashboard')

@section('title', 'Manifest: ' . $manifest->manifest_number)

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('operator.manifests.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
      <i class="fas fa-arrow-left me-1"></i> Back to Manifests
    </a>
    <h3 class="text-dark mb-0 fw-bold">Manifest: {{ $manifest->manifest_number }}</h3>
    <p class="text-muted small mb-0">{{ $manifest->vessel_or_vehicle_name }} &bull; Voyage: {{ $manifest->voyage_number }}</p>
  </div>
  <div class="d-flex gap-2">
    @if($manifest->status === 'PREPARING')
      <form method="POST" action="{{ route('operator.manifests.seal', $manifest->id) }}" onsubmit="return confirm('Lock and affix customs security seals to this manifest?');">
        @csrf
        <button type="submit" class="btn btn-danger btn-sm shadow-sm">
          <i class="fas fa-lock me-1"></i> Lock &amp; Seal Manifest
        </button>
      </form>
    @else
      <span class="badge bg-success px-3 py-2 fs-6"><i class="fas fa-check-shield me-1"></i> Sealed &amp; Active</span>
    @endif
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

<div class="row">
  <!-- Left Column: Manifest Specs & Allocated Containers -->
  <div class="col-lg-8">
    <!-- Corridor & Schedule Card -->
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-route me-1"></i> Voyage Corridor Schedule</h6>
        @php
          $badgeClass = match($manifest->status) {
            'PREPARING' => 'bg-warning text-dark',
            'LOCKED_SEALED' => 'bg-info',
            'SAILING' => 'bg-primary',
            'ARRIVED' => 'bg-success',
            default => 'bg-secondary'
          };
        @endphp
        <span class="badge {{ $badgeClass }} px-2 py-1">{{ str_replace('_', ' ', $manifest->status) }}</span>
      </div>
      <div class="card-body">
        <div class="row text-center py-2 bg-light rounded mb-3">
          <div class="col-5">
            <span class="badge bg-secondary">{{ $manifest->origin_port_id }}</span>
            <h6 class="fw-bold mb-0 mt-1">{{ $manifest->originPort?->name }}</h6>
            <small class="text-muted">ETD: {{ \Carbon\Carbon::parse($manifest->departure_eta)->format('d M Y, H:i') }}</small>
          </div>
          <div class="col-2 align-self-center">
            <i class="fas fa-ship fa-2x text-primary"></i>
          </div>
          <div class="col-5">
            <span class="badge bg-primary">{{ $manifest->destination_port_id }}</span>
            <h6 class="fw-bold mb-0 mt-1">{{ $manifest->destinationPort?->name }}</h6>
            <small class="text-muted">ETA: {{ \Carbon\Carbon::parse($manifest->arrival_eta)->format('d M Y, H:i') }}</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Allocated Containers & Consignments -->
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
          <i class="fas fa-cubes me-1"></i> Allocated Consignments ({{ $manifest->manifestItems->count() }})
        </h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Container Serial</th>
                <th>Type</th>
                <th>Security Seal</th>
                <th>Booking Ref</th>
                <th>Shipper</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              @forelse($manifest->manifestItems as $item)
                <tr>
                  <td class="ps-3 fw-bold text-primary">{{ $item->container_serial_number }}</td>
                  <td><span class="badge bg-light text-dark border">{{ $item->container_type }}</span></td>
                  <td>
                    @if($item->seal_number === 'PENDING_SEAL')
                      <span class="badge bg-warning text-dark">PENDING LOCK</span>
                    @else
                      <span class="badge bg-danger">{{ $item->seal_number }}</span>
                    @endif
                  </td>
                  <td>
                    @if($item->cargoBooking)
                      <a href="{{ route('operator.bookings.show', $item->cargoBooking->id) }}" class="fw-bold text-decoration-none">
                        {{ $item->cargoBooking->tracking_number }}
                      </a>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td class="small">{{ $item->cargoBooking?->shipper?->name ?? 'Shipper' }}</td>
                  <td>
                    <span class="badge bg-info">{{ $item->cargoBooking?->cargo_status ?? 'N/A' }}</span>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center py-5 text-muted">
                    <i class="fas fa-box-open fa-3x mb-3 text-gray-300 d-block"></i>
                    No containers allocated to this manifest yet.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Column: Quick Consignment Allocation -->
  <div class="col-lg-4">
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-plus-circle me-1"></i> Allocate Booking</h6>
      </div>
      <div class="card-body">
        @if($manifest->status !== 'PREPARING')
          <div class="alert alert-secondary small mb-0">
            <i class="fas fa-lock me-1"></i> This manifest is locked and sealed. Additional consignments cannot be added without supervisor override.
          </div>
        @elseif($availableBookings->isEmpty())
          <div class="alert alert-info small mb-0">
            <i class="fas fa-info-circle me-1"></i> No unmanifested bookings found matching this route ({{ $manifest->origin_port_id }} &rarr; {{ $manifest->destination_port_id }}).
          </div>
        @else
          <form method="POST" action="" id="assignBookingForm">
            @csrf
            <input type="hidden" name="manifest_id" value="{{ $manifest->id }}">

            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Select Available Consignment</label>
              <select name="booking_id" id="bookingSelect" class="form-select form-select-sm" required onchange="updateFormAction(this.value)">
                <option value="">-- Choose Consignment --</option>
                @foreach($availableBookings as $b)
                  <option value="{{ $b->id }}">
                    {{ $b->tracking_number }} — {{ number_format($b->total_weight_kg, 1) }} kg ({{ $b->shipper?->name }})
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Container Serial (ISO 6346)</label>
              <input type="text" name="container_serial_number" value="FCGU-{{ rand(100000, 999999) }}-{{ rand(0, 9) }}" class="form-control form-control-sm" required>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Container Equipment Type</label>
              <select name="container_type" class="form-select form-select-sm">
                <option value="DRY_20FT">Dry 20FT Standard</option>
                <option value="DRY_40FT" selected>Dry 40FT High Cube</option>
                <option value="REEFER_40HC">Reefer 40HC (Cold Chain)</option>
              </select>
            </div>

            <button type="submit" class="btn btn-primary btn-sm w-100">
              <i class="fas fa-link me-1"></i> Attach to Manifest
            </button>
          </form>
          <script>
            function updateFormAction(bookingId) {
              if (bookingId) {
                document.getElementById('assignBookingForm').action = '/bookings/' + bookingId + '/assign';
              }
            }
          </script>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

