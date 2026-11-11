@extends('layouts.dashboard')

@section('title', 'Corporate KYC & AML Review')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Corporate KYC &amp; Trade License Review</h3>
    <p class="text-muted small mb-0">Verify corporate entity identities, NIB, NPWP, and ASEAN Single Window cross-border trade qualifications.</p>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if(session('warning'))
  <div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-1"></i> {{ session('warning') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white">
    <h6 class="m-0 font-weight-bold text-primary">Corporate Entity Verification Queue</h6>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Authorized Representative</th>
            <th>Registered Entity</th>
            <th>NIB (Nomor Induk Berusaha)</th>
            <th>Corporate NPWP</th>
            <th>Industry Sector</th>
            <th>Status</th>
            <th class="text-end pe-3">Compliance Decision</th>
          </tr>
        </thead>
        <tbody>
          @forelse($users as $u)
            @php $comp = $u->companies->first(); @endphp
            <tr>
              <td class="ps-3">
                <div class="fw-bold text-dark">{{ $u->name }}</div>
                <small class="text-muted">{{ $u->email }}</small>
              </td>
              <td class="fw-semibold text-primary">
                {{ $comp->company_name ?? 'Individual Shipper' }}
              </td>
              <td><code>{{ $comp->nib ?? 'PENDING' }}</code></td>
              <td><code>{{ $comp->npwp ?? 'PENDING' }}</code></td>
              <td><span class="badge bg-light text-dark border">{{ $comp->industry ?? 'General Cargo' }}</span></td>
              <td>
                @if($u->kyc_aml_status === 'VERIFIED')
                  <span class="badge bg-success">VERIFIED</span>
                @elseif($u->kyc_aml_status === 'PENDING')
                  <span class="badge bg-warning text-dark">PENDING REVIEW</span>
                @else
                  <span class="badge bg-danger">REJECTED</span>
                @endif
              </td>
              <td class="text-end pe-3">
                @if($u->kyc_aml_status !== 'VERIFIED')
                  <form method="POST" action="{{ route('admin.kyc.approve', $u->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success me-1 shadow-sm">
                      <i class="fas fa-check me-1"></i> Verify
                    </button>
                  </form>
                  <form method="POST" action="{{ route('admin.kyc.reject', $u->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                      <i class="fas fa-times"></i>
                    </button>
                  </form>
                @else
                  <span class="text-success small fw-semibold"><i class="fas fa-shield-alt"></i> Compliant</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">
                No corporate KYC submissions found.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if($users->hasPages())
    <div class="card-footer bg-white py-3">
      {{ $users->links() }}
    </div>
  @endif
</div>
@endsection

