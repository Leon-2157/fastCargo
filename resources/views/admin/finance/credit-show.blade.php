@extends('layouts.dashboard')

@section('title', 'Corporate Credit Facility — ' . ($credit->company?->company_name ?? 'Underwriting'))

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('admin.credits.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
      <i class="fas fa-arrow-left me-1"></i> Back to Corporate Credit Lines
    </a>
    <h3 class="text-dark mb-0 fw-bold">Credit Underwriting: {{ $credit->company?->company_name ?? 'Corporate Entity' }}</h3>
    <p class="text-muted small mb-0">Configure B2B credit ceiling, Terms of Payment (TOP), bank guarantee reference, and debt enforcement posture.</p>
  </div>
  <div>
    <span class="badge {{ $credit->kyc_status === 'VERIFIED_APPROVED' ? 'bg-success' : 'bg-warning text-dark' }} px-3 py-2 fs-6">
      <i class="fas fa-shield-alt me-1"></i> {{ $credit->kyc_status }}
    </span>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@php
  $limit = (float) $credit->credit_limit_amount;
  $used = (float) $credit->used_credit_amount;
  $available = (float) $credit->availableCredit();
  $utilizationPct = $limit > 0 ? min(100, round(($used / $limit) * 100, 1)) : 0;
@endphp

<!-- Utilization KPI Row -->
<div class="row mb-4">
  <div class="col-md-4 mb-2">
    <div class="card shadow-sm border-start-primary">
      <div class="card-body">
        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Approved Credit Ceiling</div>
        <div class="h5 mb-0 fw-bold text-gray-800">IDR {{ number_format($limit, 0, ',', '.') }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card shadow-sm border-start-warning">
      <div class="card-body">
        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Utilized Exposure ({{ $utilizationPct }}%)</div>
        <div class="h5 mb-0 fw-bold text-warning">IDR {{ number_format($used, 0, ',', '.') }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card shadow-sm border-start-success">
      <div class="card-body">
        <div class="text-xs fw-bold text-success text-uppercase mb-1">Available Credit Headroom</div>
        <div class="h5 mb-0 fw-bold text-success">IDR {{ number_format($available, 0, ',', '.') }}</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Left Column: Underwriting Configuration Form -->
  <div class="col-lg-6">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-sliders-h me-2"></i>Underwriting &amp; Risk Governance Parameters</h6>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin.credits.update', $credit->id) }}">
          @csrf
          @method('PUT')

          <div class="mb-3">
            <label for="creditLimitAmount" class="form-label small fw-bold text-dark">Credit Limit Ceiling (IDR)</label>
            <input
              type="number"
              id="creditLimitAmount"
              name="credit_limit_amount"
              class="form-control form-control-sm"
              min="0"
              step="100000"
              value="{{ old('credit_limit_amount', (int) $credit->credit_limit_amount) }}"
              required
            >
          </div>

          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label for="topDaysSelect" class="form-label small fw-bold text-dark">Terms of Payment (TOP)</label>
              <select id="topDaysSelect" name="top_days" class="form-select form-select-sm" required>
                <option value="30" {{ (string) $credit->top_days === '30' ? 'selected' : '' }}>30 Days Net</option>
                <option value="45" {{ (string) $credit->top_days === '45' ? 'selected' : '' }}>45 Days Net</option>
                <option value="60" {{ (string) $credit->top_days === '60' ? 'selected' : '' }}>60 Days Net</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label for="kycStatusSelect" class="form-label small fw-bold text-dark">KYC Underwriting Status</label>
              <select id="kycStatusSelect" name="kyc_status" class="form-select form-select-sm" required>
                <option value="VERIFIED_APPROVED" {{ $credit->kyc_status === 'VERIFIED_APPROVED' ? 'selected' : '' }}>VERIFIED_APPROVED</option>
                <option value="PENDING_VERIFICATION" {{ $credit->kyc_status === 'PENDING_VERIFICATION' ? 'selected' : '' }}>PENDING_VERIFICATION</option>
                <option value="REJECTED" {{ $credit->kyc_status === 'REJECTED' ? 'selected' : '' }}>REJECTED</option>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label for="debtEnforcementStageSelect" class="form-label small fw-bold text-dark">Debt Enforcement Stage</label>
              <select id="debtEnforcementStageSelect" name="debt_enforcement_stage" class="form-select form-select-sm">
                <option value="NORMAL" {{ $credit->debt_enforcement_stage === 'NORMAL' ? 'selected' : '' }}>NORMAL (Good Standing)</option>
                <option value="WARNING_LETTER_1" {{ $credit->debt_enforcement_stage === 'WARNING_LETTER_1' ? 'selected' : '' }}>WARNING_LETTER_1 (SP-1 Issued)</option>
                <option value="ACCOUNT_FROZEN" {{ $credit->debt_enforcement_stage === 'ACCOUNT_FROZEN' ? 'selected' : '' }}>ACCOUNT_FROZEN (Booking Hold)</option>
                <option value="LEGAL_DEFAULT" {{ $credit->debt_enforcement_stage === 'LEGAL_DEFAULT' ? 'selected' : '' }}>LEGAL_DEFAULT (Legal Recovery)</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label for="bankGuaranteeRefInput" class="form-label small fw-bold text-dark">Bank Guarantee / Collateral Ref</label>
              <input
                type="text"
                id="bankGuaranteeRefInput"
                name="bank_guarantee_ref"
                class="form-control form-control-sm"
                placeholder="e.g. BG-MANDIRI-2026-8841"
                value="{{ old('bank_guarantee_ref', $credit->bank_guarantee_ref) }}"
              >
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center pt-2 border-top">
            <span class="text-muted small">
              Last Verified By: <strong>{{ $credit->verifiedByAdmin?->name ?? 'System Provisioner' }}</strong>
            </span>
            <button type="submit" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm">
              <i class="fas fa-save me-1"></i> Save Credit Parameters
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Right Column: Corporate Legal Profile & Recent Invoices -->
  <div class="col-lg-6">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-building me-2"></i>Corporate Legal Entity Dossier</h6>
      </div>
      <div class="card-body">
        @if($credit->company)
          <dl class="row mb-0 small">
            <dt class="col-sm-4 text-muted">Legal Name</dt>
            <dd class="col-sm-8 fw-bold text-dark">{{ $credit->company->company_name }}</dd>

            <dt class="col-sm-4 text-muted">NPWP / Tax ID</dt>
            <dd class="col-sm-8"><code>{{ $credit->company->npwp ?? 'N/A' }}</code></dd>

            <dt class="col-sm-4 text-muted">NIB License</dt>
            <dd class="col-sm-8"><code>{{ $credit->company->nib ?? 'N/A' }}</code></dd>

            <dt class="col-sm-4 text-muted">Primary Account</dt>
            <dd class="col-sm-8">
              @if($credit->company->user)
                <a href="{{ route('admin.kyc.show', $credit->company->user->id) }}" class="text-decoration-none fw-semibold">
                  {{ $credit->company->user->name }} (Open KYC Dossier)
                </a>
                <div class="text-muted">{{ $credit->company->user->email }}</div>
              @else
                <span class="text-muted">—</span>
              @endif
            </dd>

            <dt class="col-sm-4 text-muted">Billing Address</dt>
            <dd class="col-sm-8 mb-0">{{ $credit->company->billing_address ?? '—' }}</dd>
          </dl>
        @else
          <p class="text-muted small mb-0">No company record attached.</p>
        @endif
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-file-invoice-dollar me-2"></i>Recent Corporate Invoices</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Invoice #</th>
                <th>Amount</th>
                <th>Due Date</th>
                <th class="pe-3">Status</th>
              </tr>
            </thead>
            <tbody>
              @forelse($credit->company?->invoices ?? [] as $inv)
                <tr>
                  <td class="ps-3 fw-bold">
                    <a href="{{ route('admin.invoices.show', $inv->id) }}" class="text-decoration-none">{{ $inv->invoice_number }}</a>
                  </td>
                  <td class="fw-bold">IDR {{ number_format($inv->total_invoice_amount, 0, ',', '.') }}</td>
                  <td>{{ $inv->due_date ? \Carbon\Carbon::parse($inv->due_date)->format('d M Y') : '—' }}</td>
                  <td class="pe-3">
                    @if($inv->payment_status === 'PAID_SETTLED')
                      <span class="badge bg-success">PAID_SETTLED</span>
                    @else
                      <span class="badge bg-warning text-dark">UNPAID</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="text-center py-4 text-muted">No invoices billed to this corporate entity yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

