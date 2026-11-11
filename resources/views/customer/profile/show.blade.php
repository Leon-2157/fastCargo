@extends('layouts.dashboard')

@section('title', 'Account & KYC Verification')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Account Profile &amp; Compliance</h3>
    <p class="text-muted small mb-0">Manage your shipper identity, timezone preferences, and ASEAN corporate trade verification.</p>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="row">
  <!-- Profile & Timezone -->
  <div class="col-lg-6 mb-4">
    <div class="card shadow">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user-circle me-1"></i> Personal Identity</h6>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('customer.profile.update') }}">
          @csrf
          @method('PUT')

          <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Full Name</label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Email Address</label>
            <input type="email" value="{{ $user->email }}" class="form-control bg-light" disabled>
            <small class="text-muted">Email verification: <span class="text-success fw-bold"><i class="fas fa-check-circle"></i> Verified</span></small>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Contact Phone Number</label>
            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control" placeholder="+628123456789">
          </div>

          <div class="mb-4">
            <label class="form-label text-muted small fw-bold">Operational Timezone</label>
            <select name="timezone" class="form-select">
              <option value="Asia/Jakarta" {{ $user->timezone === 'Asia/Jakarta' ? 'selected' : '' }}>Asia/Jakarta (WIB, UTC+7) — Indonesia Barat, Thailand, Vietnam</option>
              <option value="Asia/Makassar" {{ $user->timezone === 'Asia/Makassar' ? 'selected' : '' }}>Asia/Makassar (WITA, UTC+8) — Singapore, Malaysia, Philippines</option>
              <option value="Asia/Jayapura" {{ $user->timezone === 'Asia/Jayapura' ? 'selected' : '' }}>Asia/Jayapura (WIT, UTC+9) — Indonesia Timur</option>
              <option value="Asia/Bangkok" {{ $user->timezone === 'Asia/Bangkok' ? 'selected' : '' }}>Asia/Bangkok (UTC+7)</option>
              <option value="Asia/Singapore" {{ $user->timezone === 'Asia/Singapore' ? 'selected' : '' }}>Asia/Singapore (SGT, UTC+8)</option>
            </select>
            <small class="text-muted">All shipment arrival and departure ETDs will be localized to this timezone.</small>
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save Changes
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Corporate KYC & Trade Compliance -->
  <div class="col-lg-6 mb-4">
    <div class="card shadow">
      <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-building me-1"></i> Corporate Entity &amp; Trade License</h6>
        <span class="badge {{ $user->kyc_aml_status === 'VERIFIED' ? 'bg-success' : 'bg-warning text-dark' }}">
          KYC: {{ $user->kyc_aml_status ?? 'UNVERIFIED' }}
        </span>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('customer.profile.upgrade') }}">
          @csrf

          <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Registered Legal Entity Name</label>
            <input type="text" name="company_name" value="{{ old('company_name', $company->company_name ?? '') }}" class="form-control" placeholder="PT / Ltd / Co" required>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-bold">NIB (Nomor Induk Berusaha)</label>
              <input type="text" name="nib" value="{{ old('nib', $company->nib ?? '') }}" class="form-control" placeholder="13 digits NIB" required>
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-bold">Corporate NPWP / Tax ID</label>
              <input type="text" name="npwp" value="{{ old('npwp', $company->npwp ?? '') }}" class="form-control" placeholder="01.234.567.8-012.000" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Registered Corporate Address</label>
            <textarea name="company_address" class="form-control" rows="2" required>{{ old('company_address', $company->company_address ?? '') }}</textarea>
          </div>

          <div class="mb-4">
            <label class="form-label text-muted small fw-bold">Primary Industry Sector</label>
            <select name="industry" class="form-select">
              <option value="Manufacturing & Electronics" {{ ($company->industry ?? '') === 'Manufacturing & Electronics' ? 'selected' : '' }}>Manufacturing &amp; Electronics</option>
              <option value="Agriculture & Food" {{ ($company->industry ?? '') === 'Agriculture & Food' ? 'selected' : '' }}>Agriculture, Commodities &amp; Food</option>
              <option value="Automotive & Parts" {{ ($company->industry ?? '') === 'Automotive & Parts' ? 'selected' : '' }}>Automotive &amp; Machinery</option>
              <option value="Textiles & Apparel" {{ ($company->industry ?? '') === 'Textiles & Apparel' ? 'selected' : '' }}>Textiles &amp; Garments</option>
              <option value="Chemicals & Plastics" {{ ($company->industry ?? '') === 'Chemicals & Plastics' ? 'selected' : '' }}>Chemicals &amp; Industrial Materials</option>
              <option value="Retail & E-commerce" {{ ($company->industry ?? '') === 'Retail & E-commerce' ? 'selected' : '' }}>Retail &amp; Cross-Border E-commerce</option>
            </select>
          </div>

          <button type="submit" class="btn btn-outline-primary">
            <i class="fas fa-file-contract me-1"></i> Update Corporate KYC Information
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

