@extends('layouts.dashboard')

@section('title', 'Corporate KYC & AML Review')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-dark mb-0 fw-bold">Corporate KYC &amp; Trade License Review</h3>
        <p class="text-muted small mb-0">Verify corporate entity identities, NIB, NPWP, and ASEAN Single Window cross-border trade qualifications.</p>
    </div>
</div>

@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-1"></i> {{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- KYC Summary KPI Cards -->
<div class="row mb-4">
    <div class="col-md-3 col-6 mb-2">
        <div class="card shadow-sm border-start-primary">
            <div class="card-body py-3">
                <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Customer Entities</div>
                <div class="h5 mb-0 fw-bold text-dark">{{ $stats['total'] ?? 0 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="card shadow-sm border-start-warning">
            <div class="card-body py-3">
                <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending KYC Review</div>
                <div class="h5 mb-0 fw-bold text-dark">{{ $stats['pending'] ?? 0 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="card shadow-sm border-start-success">
            <div class="card-body py-3">
                <div class="text-xs fw-bold text-success text-uppercase mb-1">Verified &amp; Approved</div>
                <div class="h5 mb-0 fw-bold text-success">{{ $stats['verified'] ?? 0 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="card shadow-sm border-start-danger">
            <div class="card-body py-3">
                <div class="text-xs fw-bold text-danger text-uppercase mb-1">Rejected</div>
                <div class="h5 mb-0 fw-bold text-danger">{{ $stats['rejected'] ?? 0 }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 bg-white">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-md-auto">
                <h6 class="m-0 font-weight-bold text-primary">Corporate Entity Verification Queue</h6>
            </div>
            <div class="col-md-auto d-flex flex-wrap gap-2 align-items-center">
                <form method="GET" action="{{ route('admin.kyc.index') }}" class="d-flex gap-1">
                    @if(request('status'))
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif
                    <label for="kyc_search" class="visually-hidden">Search KYC entities by company, NIB, NPWP, or representative</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="kyc_search" name="search" value="{{ request('search') }}"
                            class="form-control" placeholder="Search company, NIB, NPWP...">
                        <button type="submit" class="btn btn-outline-primary" aria-label="Search KYC entities">
                            <i class="fas fa-search"></i>
                        </button>
                        @if(request('search'))
                            <a href="{{ route('admin.kyc.index', ['status' => request('status')]) }}" class="btn btn-outline-secondary" title="Clear search">
                                <i class="fas fa-times"></i>
                            </a>
                        @endif
                    </div>
                </form>
                <div class="d-flex flex-wrap gap-1">
                    <a href="{{ route('admin.kyc.index', ['search' => request('search')]) }}"
                        class="btn btn-sm {{ !request('status') || request('status') === 'ALL' ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
                    <a href="{{ route('admin.kyc.index', ['status' => 'PENDING', 'search' => request('search')]) }}"
                        class="btn btn-sm {{ request('status') === 'PENDING' ? 'btn-warning text-dark' : 'btn-outline-warning' }}">Pending</a>
                    <a href="{{ route('admin.kyc.index', ['status' => 'VERIFIED', 'search' => request('search')]) }}"
                        class="btn btn-sm {{ request('status') === 'VERIFIED' ? 'btn-success' : 'btn-outline-success' }}">Verified</a>
                    <a href="{{ route('admin.kyc.index', ['status' => 'REJECTED', 'search' => request('search')]) }}"
                        class="btn btn-sm {{ request('status') === 'REJECTED' ? 'btn-danger' : 'btn-outline-danger' }}">Rejected</a>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Authorized Representative</th>
                        <th>Registered Entity</th>
                        <th>NIB (Business ID)</th>
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
                                <a href="{{ route('admin.kyc.show', $u->id) }}" class="fw-bold text-dark text-decoration-none">{{ $u->name }}</a>
                                <div class="small text-muted">{{ $u->email }}</div>
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
                                    <span class="badge bg-danger">{{ $u->kyc_aml_status ?? 'REJECTED' }}</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex gap-1 align-items-center">
                                    <a href="{{ route('admin.kyc.show', $u->id) }}" class="btn btn-sm btn-outline-primary" title="Inspect Corporate Dossier">
                                        <i class="fas fa-eye me-1"></i> Inspect
                                    </a>
                                    @if($u->kyc_aml_status !== 'VERIFIED')
                                        <form method="POST" action="{{ route('admin.kyc.approve', $u->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success shadow-sm">
                                                <i class="fas fa-check me-1"></i> Verify
                                            </button>
                                        </form>
                                        @if($u->kyc_aml_status !== 'REJECTED')
                                            <form method="POST" action="{{ route('admin.kyc.reject', $u->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject KYC">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        <span class="text-success small fw-semibold ms-1"><i class="fas fa-shield-alt"></i> Compliant</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-file-signature fa-2x mb-2 text-gray-300 d-block"></i>
                                No customer or corporate KYC submissions found for the selected filter.
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
