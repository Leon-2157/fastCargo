@extends('layouts.dashboard')

@section('title', 'Verify Consignment: ' . $booking->tracking_number)

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('operator.bookings.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
      <i class="fas fa-arrow-left me-1"></i> Back to Consignments
    </a>
    <h3 class="text-dark mb-0 fw-bold">Inspection Terminal: {{ $booking->tracking_number }}</h3>
    <p class="text-muted small mb-0">Shipper: <strong>{{ $booking->shipper?->name }}</strong> ({{ $booking->company?->company_name ?? 'Individual' }})</p>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('operator.epod.form', $booking->id) }}" class="btn btn-success btn-sm shadow-sm">
      <i class="fas fa-signature me-1"></i> ePOD Terminal
    </a>
    <a href="{{ route('operator.incidents.create', $booking->id) }}" class="btn btn-outline-danger btn-sm shadow-sm">
      <i class="fas fa-exclamation-triangle me-1"></i> File Incident
    </a>
  </div>
</div>

@if(session('warning'))
  <div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-1"></i> {{ session('warning') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="row">
  <!-- Left Column: Cargo Review & Items -->
  <div class="col-lg-8">
    <!-- Corridor & Classification Details -->
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-clipboard-check me-1"></i> Consignment Specifications</h6>
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
        <span class="badge {{ $badgeClass }} px-3 py-2 fs-6">
          {{ str_replace('_', ' ', $booking->cargo_status) }}
        </span>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <span class="text-muted small d-block">Origin Hub:</span>
            <span class="fw-bold">{{ $booking->route?->origin_port_id }} — {{ $booking->route?->originPort?->city }}</span>
          </div>
          <div class="col-md-4">
            <span class="text-muted small d-block">Destination Hub:</span>
            <span class="fw-bold">{{ $booking->route?->destination_port_id }} — {{ $booking->route?->destinationPort?->city }}</span>
          </div>
          <div class="col-md-4">
            <span class="text-muted small d-block">Transport Mode:</span>
            <span class="badge bg-primary">{{ $booking->route?->transport_mode ?? 'SEA' }} Corridor</span>
          </div>

          <div class="col-md-4">
            <span class="text-muted small d-block">Booking Model:</span>
            <span class="fw-bold">{{ str_replace('_', ' ', $booking->booking_type) }}</span>
          </div>
          <div class="col-md-4">
            <span class="text-muted small d-block">Cargo Classification:</span>
            <span class="fw-bold">{{ str_replace('_', ' ', $booking->cargo_type) }}</span>
          </div>
          <div class="col-md-4">
            <span class="text-muted small d-block">HS Tariff Code:</span>
            <span class="fw-bold">{{ $booking->hs_code ?? '8471.30' }}</span>
          </div>

          <div class="col-md-4">
            <span class="text-muted small d-block">Gross Weight:</span>
            <span class="fw-bold text-dark">{{ number_format($booking->total_weight_kg, 2) }} KG</span>
          </div>
          <div class="col-md-4">
            <span class="text-muted small d-block">Total Volumetric Metric:</span>
            <span class="fw-bold text-dark">{{ number_format($booking->total_volume_cbm, 4) }} CBM</span>
          </div>
          <div class="col-md-4">
            <span class="text-muted small d-block">Declared Goods Value:</span>
            <span class="fw-bold text-success">IDR {{ number_format($booking->declared_goods_value, 0, ',', '.') }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Items Packing List -->
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-boxes me-1"></i> Packing List Inspection</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Item Description</th>
                <th>Package Unit</th>
                <th>Qty</th>
                <th>Weight (KG)</th>
                <th>Dimensions</th>
                <th>CBM</th>
                <th>Dangerous Goods</th>
              </tr>
            </thead>
            <tbody>
              @foreach($booking->items as $item)
                <tr>
                  <td class="ps-3 fw-bold">{{ $item->item_name }}</td>
                  <td>{{ $item->package_type }}</td>
                  <td>{{ $item->quantity }}</td>
                  <td>{{ number_format($item->weight_kg, 2) }}</td>
                  <td>{{ $item->length_cm }} × {{ $item->width_cm }} × {{ $item->height_cm }} cm</td>
                  <td>{{ number_format($item->volume_cbm, 4) }}</td>
                  <td>
                    @if($item->is_dangerous_good)
                      <span class="badge bg-danger">HAZMAT</span>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Milestone Events & Manual Milestone Append Form -->
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-map-pin me-1"></i> Append Milestone Event</h6>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('operator.tracking.event', $booking->id) }}">
          @csrf
          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label small text-muted">Event Milestone Description</label>
              <input type="text" name="event_name" class="form-control form-control-sm" placeholder="e.g. Cleared Customs Inspection at Gate 03" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small text-muted">Target Operational Status</label>
              <select name="current_status" class="form-select form-select-sm" required>
                <option value="GATE_IN">GATE_IN (Port Entry)</option>
                <option value="CUSTOMS_INSPECTION">CUSTOMS_INSPECTION (Inspection Area)</option>
                <option value="CUSTOMS_CLEARED">CUSTOMS_CLEARED (Clearance Issued)</option>
                <option value="SAILING">SAILING (Vessel Departed)</option>
                <option value="ARRIVED">ARRIVED (Destination Port)</option>
                <option value="WEATHER_HOLD">WEATHER_HOLD (Meteorological Delay)</option>
                <option value="OUT_FOR_DELIVERY">OUT_FOR_DELIVERY (Final Mile)</option>
              </select>
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small text-muted">GPS Latitude</label>
              <input type="number" step="0.0000001" name="latitude" value="{{ $booking->route?->originPort?->latitude ?? -6.1050 }}" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small text-muted">GPS Longitude</label>
              <input type="number" step="0.0000001" name="longitude" value="{{ $booking->route?->originPort?->longitude ?? 106.8847 }}" class="form-control form-control-sm" required>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-plus-circle me-1"></i> Log Milestone &amp; Broadcast Telemetry
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Right Column: Operator Actions -->
  <div class="col-lg-4">
    <!-- Customs Channel & Approval Form -->
    <div class="card shadow mb-4 border-start-warning">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-shield-alt me-1"></i> Customs Channel &amp; Review</h6>
      </div>
      <div class="card-body">
        @if($booking->cargo_status === 'DRAFT')
          <form method="POST" action="{{ route('operator.bookings.approve', $booking->id) }}" class="mb-3">
            @csrf
            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Select Customs Clearance Channel</label>
              <select name="customs_channel" class="form-select form-select-sm">
                <option value="GREEN_CHANNEL" selected>GREEN CHANNEL (Documentary Direct Release)</option>
                <option value="YELLOW_CHANNEL">YELLOW CHANNEL (Document Audit Required)</option>
                <option value="RED_CHANNEL">RED CHANNEL (Physical Container Inspection)</option>
              </select>
            </div>
            <button type="submit" class="btn btn-success btn-sm w-100 shadow mb-2">
              <i class="fas fa-check-circle me-1"></i> Approve Consignment Booking
            </button>
          </form>

          <form method="POST" action="{{ route('operator.bookings.reject', $booking->id) }}" onsubmit="return confirm('Reject this consignment?');">
            @csrf
            <div class="mb-2">
              <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason for rejection (e.g. prohibited goods)" required>
            </div>
            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
              <i class="fas fa-times me-1"></i> Reject Consignment
            </button>
          </form>
        @else
          <div class="alert alert-success small mb-0">
            <i class="fas fa-check-circle me-1"></i> Consignment approved. Current Customs Channel:
            <strong>{{ $booking->customs_channel ?? 'GREEN_CHANNEL' }}</strong>.
          </div>
        @endif
      </div>
    </div>

    <!-- Allocate to Manifest Form -->
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-ship me-1"></i> Allocate to Open Manifest</h6>
      </div>
      <div class="card-body">
        @if($openManifests->isEmpty())
          <div class="alert alert-light small mb-0 border">
            No open preparing manifests on this corridor ({{ $booking->route?->origin_port_id }} &rarr; {{ $booking->route?->destination_port_id }}).
            <a href="{{ route('operator.manifests.create') }}" class="fw-bold d-block mt-1">Create New Manifest</a>
          </div>
        @else
          <form method="POST" action="{{ route('operator.bookings.assign', $booking->id) }}">
            @csrf
            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Select Manifest</label>
              <select name="manifest_id" class="form-select form-select-sm" required>
                @foreach($openManifests as $m)
                  <option value="{{ $m->id }}">{{ $m->manifest_number }} ({{ $m->vessel_or_vehicle_name }})</option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Container Serial (ISO 6346)</label>
              <input type="text" name="container_serial_number" value="FCGU-{{ rand(100000, 999999) }}-{{ rand(0, 9) }}" class="form-control form-control-sm" required>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Equipment Type</label>
              <select name="container_type" class="form-select form-select-sm">
                <option value="DRY_20FT">Dry 20FT Standard</option>
                <option value="DRY_40FT" selected>Dry 40FT High Cube</option>
                <option value="REEFER_40HC">Reefer 40HC</option>
              </select>
            </div>

            <button type="submit" class="btn btn-primary btn-sm w-100 shadow">
              <i class="fas fa-link me-1"></i> Attach to Selected Manifest
            </button>
          </form>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

