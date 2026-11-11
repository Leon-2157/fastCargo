@extends('layouts.dashboard')

@section('title', 'File Incident Escalation')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('operator.bookings.show', $booking->id) }}" class="btn btn-outline-secondary btn-sm mb-2">
      <i class="fas fa-arrow-left me-1"></i> Back to Consignment
    </a>
    <h3 class="text-dark mb-0 fw-bold">File Incident: {{ $booking->tracking_number }}</h3>
    <p class="text-muted small mb-0">Corridor: {{ $booking->route?->origin_port_id }} &rarr; {{ $booking->route?->destination_port_id }}</p>
  </div>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card shadow mb-4 border-start-danger">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Incident Report Ticket</h6>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="{{ route('operator.incidents.store') }}">
          @csrf
          <input type="hidden" name="cargo_booking_id" value="{{ $booking->id }}">

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold text-secondary">Incident Classification</label>
              <select name="incident_type" class="form-select @error('incident_type') is-invalid @enderror" required>
                <option value="WEATHER_HOLD">WEATHER_HOLD (Cyclone, High Swell, Severe Storm Delay)</option>
                <option value="FIELD_COST">FIELD_COST (Emergency Port Handling / Demurrage Fee)</option>
                <option value="DAMAGE_CLAIM">DAMAGE_CLAIM (Physical Container or Cargo Packaging Damage)</option>
                <option value="SLA_BREACH">SLA_BREACH (Customs Red Channel Prolonged Hold)</option>
              </select>
              @error('incident_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold text-secondary">Emergency Requested Amount (IDR, if applicable)</label>
              <input type="number" step="10000" name="requested_amount" value="{{ old('requested_amount', 0) }}" class="form-control" placeholder="0 if no cost impact">
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold text-secondary">Official Incident Rationale / BAP Description</label>
            <textarea name="description" rows="4" class="form-control @error('description') is-invalid @enderror"
                      placeholder="Detailed statement explaining circumstances, exact coordinates or port berth, and remedial measures requested..." required>{{ old('description') }}</textarea>
            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <button type="submit" class="btn btn-danger px-4 shadow">
            <i class="fas fa-paper-plane me-1"></i> Submit Escalation to Admin Governance
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

