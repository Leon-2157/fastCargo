@extends('layouts.dashboard')

@section('title', 'Terminal Officer Profile')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Terminal Officer Profile &amp; Duty Station</h3>
    <p class="text-muted small mb-0">Assigned terminal, security key, and station duty status.</p>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="row">
  <!-- Officer Duty & Credentials -->
  <div class="col-lg-6 mb-4">
    <div class="card shadow">
      <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-id-badge me-1"></i> Field Officer Identification</h6>
        <span class="badge {{ ($profile->is_on_duty ?? true) ? 'bg-success' : 'bg-secondary' }} px-3 py-2">
          {{ ($profile->is_on_duty ?? true) ? 'ON DUTY' : 'OFF DUTY' }}
        </span>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <span class="text-muted small d-block">Employee ID:</span>
          <span class="fw-bold fs-5 text-dark">{{ $profile->employee_id ?? 'OPS-JKT-001' }}</span>
        </div>

        <div class="mb-3">
          <span class="text-muted small d-block">Assigned Hub / Port:</span>
          <span class="fw-bold">{{ $profile->assigned_hub_id ?? 'IDJKT' }} — Tanjung Priok Container Terminal</span>
        </div>

        <div class="mb-3">
          <span class="text-muted small d-block">Operational Role Tier:</span>
          <span class="badge bg-primary">{{ $profile->role_tier ?? 'OPERATOR_INTERNATIONAL' }}</span>
        </div>

        <div class="mb-4">
          <span class="text-muted small d-block">Officer KPI Rating:</span>
          <div class="d-flex align-items-center">
            <span class="fw-bold text-warning me-2 fs-5"><i class="fas fa-star"></i> {{ number_format($profile->kpi_rating ?? 5.0, 2) }} / 5.00</span>
            <small class="text-muted">(High dispatch speed &amp; seal integrity)</small>
          </div>
        </div>

        <form method="POST" action="{{ route('operator.profile.duty') }}">
          @csrf
          <button type="submit" class="btn {{ ($profile->is_on_duty ?? true) ? 'btn-outline-danger' : 'btn-success' }} w-100">
            <i class="fas fa-power-off me-1"></i>
            {{ ($profile->is_on_duty ?? true) ? 'Clock Out / Go OFF DUTY' : 'Clock In / Start Active Station DUTY' }}
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Contact Update -->
  <div class="col-lg-6 mb-4">
    <div class="card shadow">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user-edit me-1"></i> Edit Contact Credentials</h6>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('operator.profile.update') }}">
          @csrf
          @method('PUT')

          <div class="mb-3">
            <label class="form-label small fw-bold text-muted">Officer Full Name</label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-muted">Email Address (Login)</label>
            <input type="email" value="{{ $user->email }}" class="form-control bg-light" disabled>
          </div>

          <div class="mb-4">
            <label class="form-label small fw-bold text-muted">Emergency Dispatch Mobile Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control" required>
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Update Contact Info
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

