@extends('layouts.dashboard')

@section('title', 'Voyage & Haulage Manifests')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Voyage &amp; Haulage Manifests</h3>
    <p class="text-muted small mb-0">Build, seal and dispatch multi-modal container manifests across ASEAN corridors.</p>
  </div>
  <div>
    <a class="btn btn-warning text-dark btn-sm shadow-sm fw-bold" href="{{ route('operator.manifests.create') }}">
      <i class="fas fa-plus me-1"></i> New Voyage Manifest
    </a>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<!-- Manifest Status Filters -->
<div class="row mb-4">
  <div class="col-6 col-md-3 mb-2">
    <a href="{{ route('operator.manifests.index') }}" class="card text-decoration-none shadow-sm border-start-primary {{ !request('status') || request('status') === 'ALL' ? 'bg-light' : '' }}">
      <div class="card-body py-2">
        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Manifests</div>
        <div class="h5 mb-0 fw-bold text-gray-800">{{ $manifests->total() }}</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3 mb-2">
    <a href="{{ route('operator.manifests.index', ['status' => 'PREPARING']) }}" class="card text-decoration-none shadow-sm border-start-warning {{ request('status') === 'PREPARING' ? 'bg-light' : '' }}">
      <div class="card-body py-2">
        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Open / Loading</div>
        <div class="h5 mb-0 fw-bold text-gray-800">{{ $stats['preparing'] }}</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3 mb-2">
    <a href="{{ route('operator.manifests.index', ['status' => 'LOCKED_SEALED']) }}" class="card text-decoration-none shadow-sm border-start-info {{ request('status') === 'LOCKED_SEALED' ? 'bg-light' : '' }}">
      <div class="card-body py-2">
        <div class="text-xs fw-bold text-info text-uppercase mb-1">Sealed &amp; Cleared</div>
        <div class="h5 mb-0 fw-bold text-gray-800">{{ $stats['sealed'] }}</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3 mb-2">
    <a href="{{ route('operator.manifests.index', ['status' => 'SAILING']) }}" class="card text-decoration-none shadow-sm border-start-success {{ request('status') === 'SAILING' ? 'bg-light' : '' }}">
      <div class="card-body py-2">
        <div class="text-xs fw-bold text-success text-uppercase mb-1">Sailing / In Transit</div>
        <div class="h5 mb-0 fw-bold text-gray-800">{{ $stats['sailing'] }}</div>
      </div>
    </a>
  </div>
</div>

<!-- Manifests Table -->
<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white">
    <h6 class="m-0 font-weight-bold text-primary">Registered Manifest Documents</h6>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Manifest Number</th>
            <th>Corridor (Origin &rarr; Destination)</th>
            <th>Vessel / Vehicle</th>
            <th>Voyage #</th>
            <th>ETD Departure</th>
            <th>ETA Arrival</th>
            <th>Status</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($manifests as $manifest)
            <tr>
              <td class="ps-3">
                <a href="{{ route('operator.manifests.show', $manifest->id) }}" class="fw-bold text-primary text-decoration-none">
                  {{ $manifest->manifest_number }}
                </a>
              </td>
              <td>
                <span class="badge bg-secondary">{{ $manifest->origin_port_id }}</span>
                <i class="fas fa-arrow-right mx-1 text-muted small"></i>
                <span class="badge bg-primary">{{ $manifest->destination_port_id }}</span>
                <div class="small text-muted">{{ $manifest->originPort?->city }} to {{ $manifest->destinationPort?->city }}</div>
              </td>
              <td class="fw-semibold">{{ $manifest->vessel_or_vehicle_name }}</td>
              <td><span class="badge bg-light text-dark border">{{ $manifest->voyage_number }}</span></td>
              <td>{{ \Carbon\Carbon::parse($manifest->departure_eta)->format('d M Y, H:i') }}</td>
              <td>{{ \Carbon\Carbon::parse($manifest->arrival_eta)->format('d M Y, H:i') }}</td>
              <td>
                @php
                  $badgeClass = match($manifest->status) {
                    'PREPARING' => 'bg-warning text-dark',
                    'LOCKED_SEALED' => 'bg-info',
                    'SAILING' => 'bg-primary',
                    'ARRIVED' => 'bg-success',
                    default => 'bg-secondary'
                  };
                @endphp
                <span class="badge {{ $badgeClass }} px-2 py-1">
                  {{ str_replace('_', ' ', $manifest->status) }}
                </span>
              </td>
              <td class="text-end pe-3">
                <a href="{{ route('operator.manifests.show', $manifest->id) }}" class="btn btn-sm btn-outline-primary">
                  <i class="fas fa-clipboard-check me-1"></i> Open Terminal
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center py-5 text-muted">
                <i class="fas fa-clipboard-list fa-3x mb-3 text-gray-300 d-block"></i>
                No manifests found. Create a new manifest to start allocating consignments.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if($manifests->hasPages())
    <div class="card-footer bg-white py-3">
      {{ $manifests->links() }}
    </div>
  @endif
</div>
@endsection

