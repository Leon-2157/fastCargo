@extends('layouts.dashboard')

@section('title', 'Corporate Credit Lines')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('admin.finance.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
      <i class="fas fa-arrow-left me-1"></i> Back to Finance Cockpit
    </a>
    <h3 class="text-dark mb-0 fw-bold">Corporate Credit Lines &amp; TOP Underwriting</h3>
    <p class="text-muted small mb-0">Underwriting limits, Terms of Payment (30, 45, 60 days), and debt enforcement stages.</p>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white">
    <h6 class="m-0 font-weight-bold text-primary">Approved Corporate Credit Facilities</h6>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Company Legal Entity</th>
            <th>Credit Limit</th>
            <th>Used Credit</th>
            <th>Available Credit</th>
            <th>Terms of Payment (TOP)</th>
            <th>KYC Underwriting</th>
            <th>Debt Enforcement</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($credits as $c)
            <tr>
              <td class="ps-3 fw-bold text-primary">
                <a href="{{ route('admin.credits.show', $c->id) }}" class="text-decoration-none fw-bold">
                  {{ $c->company?->company_name ?? 'Corporate Entity' }}
                </a>
                <div class="text-xs text-muted">NPWP: {{ $c->company?->npwp ?? 'N/A' }}</div>
              </td>
              <td class="fw-bold">IDR {{ number_format($c->credit_limit_amount, 0, ',', '.') }}</td>
              <td>IDR {{ number_format($c->used_credit_amount, 0, ',', '.') }}</td>
              <td class="fw-semibold text-success">IDR {{ number_format((float) $c->availableCredit(), 0, ',', '.') }}</td>
              <td><span class="badge bg-light text-dark border">{{ $c->top_days }} Days TOP</span></td>
              <td>
                <span class="badge {{ $c->kyc_status === 'VERIFIED_APPROVED' ? 'bg-success' : ($c->kyc_status === 'REJECTED' ? 'bg-danger' : 'bg-warning text-dark') }}">
                  {{ $c->kyc_status }}
                </span>
              </td>
              <td>
                @php
                  $stageClass = match($c->debt_enforcement_stage) {
                    'NORMAL' => 'bg-success',
                    'WARNING_LETTER_1' => 'bg-warning text-dark',
                    'ACCOUNT_FROZEN', 'LEGAL_DEFAULT' => 'bg-danger',
                    default => 'bg-secondary',
                  };
                @endphp
                <span class="badge {{ $stageClass }}">{{ $c->debt_enforcement_stage }}</span>
              </td>
              <td class="text-end pe-3">
                <a href="{{ route('admin.credits.show', $c->id) }}" class="btn btn-sm btn-outline-primary">
                  <i class="fas fa-sliders-h me-1"></i> Manage
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center py-5 text-muted">
                <i class="fas fa-landmark fa-2x mb-2 text-gray-300 d-block"></i>
                No corporate credit lines active. Approve a corporate KYC dossier to provision a credit facility.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if($credits->hasPages())
    <div class="card-footer bg-white py-3">
      {{ $credits->links() }}
    </div>
  @endif
</div>
@endsection
