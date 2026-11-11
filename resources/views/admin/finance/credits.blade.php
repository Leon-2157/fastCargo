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
            <th>Terms of Payment (TOP)</th>
            <th>KYC Underwriting</th>
            <th>Debt Enforcement</th>
          </tr>
        </thead>
        <tbody>
          @forelse($credits as $c)
            <tr>
              <td class="ps-3 fw-bold text-primary">{{ $c->company?->company_name }}</td>
              <td class="fw-bold">IDR {{ number_format($c->credit_limit_amount, 0, ',', '.') }}</td>
              <td>IDR {{ number_format($c->used_credit_amount, 0, ',', '.') }}</td>
              <td><span class="badge bg-light text-dark border">{{ $c->top_days }} Days TOP</span></td>
              <td>
                <span class="badge {{ $c->kyc_status === 'VERIFIED_APPROVED' ? 'bg-success' : 'bg-warning text-dark' }}">
                  {{ $c->kyc_status }}
                </span>
              </td>
              <td>
                <span class="badge bg-success">{{ $c->debt_enforcement_stage }}</span>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center py-5 text-muted">
                No corporate credit lines active.
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

