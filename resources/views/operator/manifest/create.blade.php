@extends('layouts.dashboard')

@section('title', 'Create New Voyage Manifest')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('operator.manifests.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
      <i class="fas fa-arrow-left me-1"></i> Back to Manifests
    </a>
    <h3 class="text-dark mb-0 fw-bold">Initiate Voyage Manifest</h3>
    <p class="text-muted small mb-0">Define corridor haulage schedule, assign carrier/vessel name and voyage identification.</p>
  </div>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-ship me-1"></i> Manifest Identification &amp; Corridor</h6>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="{{ route('operator.manifests.store') }}">
          @csrf

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold text-secondary">Origin Hub (Port)</label>
              <select name="origin_port_id" class="form-select @error('origin_port_id') is-invalid @enderror" required>
                @foreach($ports as $port)
                  <option value="{{ $port->id }}" {{ old('origin_port_id') === $port->id ? 'selected' : '' }}>
                    [{{ $port->id }}] {{ $port->name }} ({{ $port->city }}, {{ $port->country_code }})
                  </option>
                @endforeach
              </select>
              @error('origin_port_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold text-secondary">Destination Hub (Port)</label>
              <select name="destination_port_id" class="form-select @error('destination_port_id') is-invalid @enderror" required>
                @foreach($ports as $port)
                  <option value="{{ $port->id }}" {{ old('destination_port_id') === $port->id ? 'selected' : '' }}>
                    [{{ $port->id }}] {{ $port->name }} ({{ $port->city }}, {{ $port->country_code }})
                  </option>
                @endforeach
              </select>
              @error('destination_port_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold text-secondary">Vessel / Aircraft / Vehicle Name</label>
              <input type="text" name="vessel_or_vehicle_name" value="{{ old('vessel_or_vehicle_name', 'MV Nusantara Express') }}"
                     class="form-control @error('vessel_or_vehicle_name') is-invalid @enderror" placeholder="e.g. MV Ever Given, B777F, Cross-Border Trailer #08" required>
              @error('vessel_or_vehicle_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
              <label class="form-label fw-semibold text-secondary">Voyage / Flight #</label>
              <input type="text" name="voyage_number" value="{{ old('voyage_number', 'VOY-' . rand(100, 999) . 'E') }}"
                     class="form-control @error('voyage_number') is-invalid @enderror" placeholder="e.g. VOY-408E, GA-882" required>
              @error('voyage_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label fw-semibold text-secondary">Estimated Departure (ETD)</label>
              <input type="datetime-local" name="departure_eta" value="{{ old('departure_eta', now()->addHours(6)->format('Y-m-d\TH:i')) }}"
                     class="form-control @error('departure_eta') is-invalid @enderror" required>
              @error('departure_eta') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold text-secondary">Estimated Arrival (ETA)</label>
              <input type="datetime-local" name="arrival_eta" value="{{ old('arrival_eta', now()->addDays(2)->format('Y-m-d\TH:i')) }}"
                     class="form-control @error('arrival_eta') is-invalid @enderror" required>
              @error('arrival_eta') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
          </div>

          <button type="submit" class="btn btn-warning text-dark fw-bold px-4">
            <i class="fas fa-check-circle me-1"></i> Save &amp; Open Manifest Builder
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow border-start-warning">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-shield-alt me-1"></i> Manifest Security Standards</h6>
      </div>
      <div class="card-body small text-muted">
        <p>A Manifest acts as the legal consolidated master bill of lading for all loaded container serials and loose freight.</p>
        <ul class="ps-3 mb-0">
          <li class="mb-2">Once locked &amp; sealed, container serial numbers and security seals cannot be modified without an authorized Admin supervisor override.</li>
          <li class="mb-2">All allocated consignments will automatically transition to <code>GATE_IN</code> status.</li>
          <li>Real-time Soketi AIS telemetry will track the vessel coordinate stream.</li>
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection

