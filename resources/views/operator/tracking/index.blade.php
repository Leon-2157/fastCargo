@extends('layouts.dashboard')

@section('title', 'Milestone & Tracking Dispatch')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Milestone Events &amp; Tracking Dispatch</h3>
    <p class="text-muted small mb-0">Log real-time port/customs/transit milestones and trigger automated WhatsApp customer notifications.</p>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if($errors->any())
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong>Validation failed:</strong> {{ $errors->first() }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
    <h6 class="m-0 font-weight-bold text-primary">Active Consignments Queue ({{ $bookings->total() }})</h6>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Tracking #</th>
            <th>Corridor</th>
            <th>Status</th>
            <th>Latest Milestone</th>
            <th>Log New Milestone</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($bookings as $booking)
            @php
              $latestEvent = $booking->trackingEvents->first();
              $defaultLat = $latestEvent?->latitude ?? $booking->route?->originPort?->latitude ?? -6.1050;
              $defaultLng = $latestEvent?->longitude ?? $booking->route?->originPort?->longitude ?? 106.8847;
            @endphp
            <tr>
              <td class="ps-3">
                <a href="{{ route('operator.bookings.show', $booking->id, false) }}" class="fw-bold text-primary text-decoration-none">
                  {{ $booking->tracking_number }}
                </a>
              </td>
              <td>
                <span class="badge bg-secondary">{{ $booking->route?->origin_port_id ?? 'ORIG' }}</span>
                <i class="fas fa-arrow-right mx-1 text-muted small"></i>
                <span class="badge bg-primary">{{ $booking->route?->destination_port_id ?? 'DEST' }}</span>
              </td>
              <td>
                <span class="badge bg-info text-dark">{{ str_replace('_', ' ', $booking->cargo_status) }}</span>
              </td>
              <td class="small">
                @if($latestEvent)
                  <div class="fw-semibold">{{ $latestEvent->event_name }}</div>
                  <div class="text-muted">{{ optional($latestEvent->event_timestamp)->format('d M Y, H:i') }}</div>
                @else
                  <span class="text-muted">No milestones recorded yet</span>
                @endif
              </td>
              <td>
                <form method="POST" action="{{ route('operator.tracking.event', $booking->id, false) }}" class="row g-2 align-items-center">
                  @csrf
                  <div class="col-md-5">
                    <input
                      type="text"
                      name="event_name"
                      class="form-control form-control-sm"
                      placeholder="e.g. Cleared Singapore Customs"
                      aria-label="Milestone description for {{ $booking->tracking_number }}"
                      required
                    >
                  </div>
                  <div class="col-md-4">
                    <select name="current_status" class="form-select form-select-sm" aria-label="Status for {{ $booking->tracking_number }}">
                      <option value="GATE_IN">GATE IN</option>
                      <option value="CUSTOMS_INSPECTION">CUSTOMS INSPECTION</option>
                      <option value="CUSTOMS_CLEARED">CUSTOMS CLEARED</option>
                      <option value="SAILING" selected>SAILING</option>
                      <option value="ARRIVED">ARRIVED</option>
                      <option value="WEATHER_HOLD">WEATHER HOLD</option>
                      <option value="OUT_FOR_DELIVERY">OUT FOR DELIVERY</option>
                    </select>
                  </div>
                  <input type="hidden" name="latitude" value="{{ $defaultLat }}">
                  <input type="hidden" name="longitude" value="{{ $defaultLng }}">
                  <div class="col-md-3">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                      <i class="fas fa-plus me-1"></i> Log
                    </button>
                  </div>
                </form>
              </td>
              <td class="text-end pe-3 text-nowrap">
                <a href="{{ route('operator.epod.form', $booking->id, false) }}" class="btn btn-sm btn-outline-success">
                  <i class="fas fa-signature me-1"></i> ePOD
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center py-5 text-muted">
                <i class="fas fa-route fa-3x mb-3 text-gray-300 d-block"></i>
                No active in-transit consignments pending milestone updates.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if($bookings->hasPages())
    <div class="card-footer bg-white">
      {{ $bookings->links() }}
    </div>
  @endif
</div>
@endsection

