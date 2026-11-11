@extends('layouts.dashboard')

@section('title', 'Booking ' . $booking->tracking_number)

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('customer.bookings.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
      <i class="fas fa-arrow-left me-1"></i> Back to My Bookings
    </a>
    <h3 class="text-dark mb-0 fw-bold">Consignment {{ $booking->tracking_number }}</h3>
    <p class="text-muted small mb-0">Registered on {{ $booking->created_at->format('l, d F Y - H:i T') }}</p>
  </div>
  <div class="d-flex gap-2">
    @if($booking->invoices->isNotEmpty())
      <a href="{{ route('customer.invoices.show', $booking->invoices->first()->id) }}" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-file-invoice-dollar me-1"></i> View Invoice
      </a>
    @endif
    <a href="{{ route('tracking.public', $booking->tracking_number) }}" target="_blank" class="btn btn-primary btn-sm">
      <i class="fas fa-external-link-alt me-1"></i> Public Tracking Link
    </a>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="row">
  <!-- Left Column: Consignment Details & Tracking Milestones -->
  <div class="col-lg-8">
    <!-- Routing & Status Card -->
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-route me-1"></i> Route &amp; Transit Corridor</h6>
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
        <span class="badge {{ $badgeClass }} px-3 py-2 fs-6">
          {{ str_replace('_', ' ', $booking->cargo_status) }}
        </span>
      </div>
      <div class="card-body">
        <div class="row align-items-center text-center py-3 bg-light rounded mb-4">
          <div class="col-5">
            <span class="badge bg-secondary mb-1">{{ $booking->route?->origin_port_id }}</span>
            <h5 class="fw-bold mb-0">{{ $booking->route?->originPort?->name ?? 'Origin Hub' }}</h5>
            <small class="text-muted">{{ $booking->route?->originPort?->city }}, {{ $booking->route?->originPort?->country_code }}</small>
          </div>
          <div class="col-2">
            <i class="fas fa-arrow-right fa-2x text-primary"></i>
            <div class="small text-muted mt-1">{{ $booking->route?->transport_mode ?? 'SEA' }}</div>
          </div>
          <div class="col-5">
            <span class="badge bg-primary mb-1">{{ $booking->route?->destination_port_id }}</span>
            <h5 class="fw-bold mb-0">{{ $booking->route?->destinationPort?->name ?? 'Destination Hub' }}</h5>
            <small class="text-muted">{{ $booking->route?->destinationPort?->city }}, {{ $booking->route?->destinationPort?->country_code }}</small>
          </div>
        </div>

        <div class="row g-3 small">
          <div class="col-md-4">
            <span class="text-muted d-block">Booking Model:</span>
            <span class="fw-bold">{{ str_replace('_', ' ', $booking->booking_type) }}</span>
          </div>
          <div class="col-md-4">
            <span class="text-muted d-block">Cargo Classification:</span>
            <span class="fw-bold">{{ str_replace('_', ' ', $booking->cargo_type) }}</span>
          </div>
          <div class="col-md-4">
            <span class="text-muted d-block">Customs Clearance Channel:</span>
            <span class="badge bg-success">{{ $booking->customs_channel ?? 'GREEN_CHANNEL' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Tracking Milestones Timeline -->
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history me-1"></i> Milestone Event History</h6>
      </div>
      <div class="card-body">
        @if($booking->trackingEvents->isEmpty())
          <p class="text-muted mb-0">No operational tracking milestones recorded yet.</p>
        @else
          <ul class="list-group list-group-flush">
            @foreach($booking->trackingEvents as $event)
              <li class="list-group-item d-flex justify-content-between align-items-start px-0 py-3">
                <div class="d-flex">
                  <div class="me-3 mt-1 text-primary">
                    <i class="fas fa-check-circle fa-lg"></i>
                  </div>
                  <div>
                    <h6 class="fw-bold mb-1">{{ $event->event_name }}</h6>
                    <div class="small text-muted">
                      Status: <span class="badge bg-light text-dark border">{{ $event->current_status }}</span>
                      @if($event->latitude && $event->longitude)
                        &bull; GPS: {{ number_format($event->latitude, 4) }}, {{ number_format($event->longitude, 4) }}
                      @endif
                    </div>
                  </div>
                </div>
                <div class="text-end small text-muted">
                  {{ \Carbon\Carbon::parse($event->event_timestamp)->format('d M Y') }}<br>
                  {{ \Carbon\Carbon::parse($event->event_timestamp)->format('H:i T') }}
                </div>
              </li>
            @endforeach
          </ul>
        @endif
      </div>
    </div>

    <!-- Packing List Table -->
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-boxes me-1"></i> Consignment Packing List</h6>
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
                <th>Dimensions (L×W×H)</th>
                <th>Volume (CBM)</th>
                <th>DG</th>
              </tr>
            </thead>
            <tbody>
              @foreach($booking->items as $item)
                <tr>
                  <td class="ps-3 fw-semibold">{{ $item->item_name }}</td>
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
            <tfoot class="table-light fw-bold">
              <tr>
                <td colspan="3" class="ps-3">Total Aggregate Load</td>
                <td>{{ number_format($booking->total_weight_kg, 2) }} KG</td>
                <td></td>
                <td>{{ number_format($booking->total_volume_cbm, 4) }} CBM</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Column: Shipping Specifications & Actions -->
  <div class="col-lg-4">
    <!-- FastCargo Consignment Card -->
    <div class="card shadow mb-4 text-center">
      <div class="card-body p-4">
        <div class="mb-3">
          <i class="fas fa-qrcode fa-5x text-dark"></i>
        </div>
        <h5 class="fw-bold mb-1">{{ $booking->tracking_number }}</h5>
        <div class="text-muted small mb-3">ASEAN Multi-Modal Consignment Passport</div>
        <div class="badge bg-light text-dark border p-2 w-100 text-start small mb-3">
          <div class="d-flex justify-content-between mb-1">
            <span class="text-muted">Shipper:</span>
            <span class="fw-semibold">{{ $booking->shipper?->name }}</span>
          </div>
          <div class="d-flex justify-content-between mb-1">
            <span class="text-muted">Declared Value:</span>
            <span class="fw-semibold">IDR {{ number_format($booking->declared_goods_value, 0, ',', '.') }}</span>
          </div>
          <div class="d-flex justify-content-between">
            <span class="text-muted">HS Code:</span>
            <span class="fw-semibold">{{ $booking->hs_code ?? '8471.30' }}</span>
          </div>
        </div>

        @if(in_array($booking->cargo_status, ['DRAFT', 'BOOKED']))
          <form method="POST" action="{{ route('customer.bookings.cancel', $booking->id) }}" onsubmit="return confirm('Are you sure you want to cancel this consignment?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
              <i class="fas fa-times-circle me-1"></i> Cancel Consignment
            </button>
          </form>
        @endif
      </div>
    </div>

    <!-- Manifest Allocation Card -->
    @if($booking->manifests->isNotEmpty())
      @php $manifest = $booking->manifests->first(); @endphp
      <div class="card shadow mb-4 border-start-info">
        <div class="card-header py-3 bg-white">
          <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-ship me-1"></i> Manifest Allocation</h6>
        </div>
        <div class="card-body small">
          <div class="mb-2">
            <span class="text-muted d-block">Manifest Number:</span>
            <span class="fw-bold">{{ $manifest->manifest_number }}</span>
          </div>
          <div class="mb-2">
            <span class="text-muted d-block">Vessel / Vehicle:</span>
            <span class="fw-bold">{{ $manifest->vessel_or_vehicle_name }} ({{ $manifest->voyage_number }})</span>
          </div>
          <div class="mb-2">
            <span class="text-muted d-block">Container ISO Serial:</span>
            <span class="fw-bold text-primary">{{ $manifest->pivot->container_serial_number ?? 'FCGU-892104-7' }}</span>
          </div>
          <div class="mb-2">
            <span class="text-muted d-block">Security Seal:</span>
            <span class="fw-bold text-danger">{{ $manifest->pivot->seal_number ?? 'PENDING_SEAL' }}</span>
          </div>
          <div>
            <span class="text-muted d-block">Estimated Arrival (ETA):</span>
            <span class="fw-bold">{{ \Carbon\Carbon::parse($manifest->arrival_eta)->format('d M Y, H:i T') }}</span>
          </div>
        </div>
      </div>
    @endif

    <!-- Linked Invoices Card -->
    @if($booking->invoices->isNotEmpty())
      @php $invoice = $booking->invoices->first(); @endphp
      <div class="card shadow mb-4 border-start-warning">
        <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
          <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-file-invoice-dollar me-1"></i> Linked Invoice</h6>
          <span class="badge {{ $invoice->payment_status === 'PAID_SETTLED' ? 'bg-success' : 'bg-warning text-dark' }}">
            {{ str_replace('_', ' ', $invoice->payment_status) }}
          </span>
        </div>
        <div class="card-body">
          <div class="h5 fw-bold text-primary mb-1">
            IDR {{ number_format($invoice->total_invoice_amount, 0, ',', '.') }}
          </div>
          <div class="small text-muted mb-3">{{ $invoice->invoice_number }} &bull; Due: {{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}</div>

          <div class="d-grid gap-2">
            <a href="{{ route('customer.invoices.show', $invoice->id) }}" class="btn btn-primary btn-sm">
              <i class="fas fa-credit-card me-1"></i> Pay with QRIS
            </a>
            <a href="{{ route('customer.invoices.pdf', $invoice->id) }}" class="btn btn-outline-secondary btn-sm">
              <i class="fas fa-file-pdf me-1"></i> Download PDF Invoice
            </a>
          </div>
        </div>
      </div>
    @endif
  </div>
</div>
@endsection

