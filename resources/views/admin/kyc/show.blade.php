@extends('layouts.dashboard')

@section('title', 'Corporate KYC Dossier — ' . $user->name)

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('admin.kyc.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="fas fa-arrow-left me-1"></i> Back to KYC Queue
        </a>
        <h3 class="text-dark mb-0 fw-bold">Corporate KYC &amp; AML Compliance Dossier</h3>
        <p class="text-muted small mb-0">Inspect corporate trade credentials, tax identification, and B2B credit underwriting status.</p>
    </div>
</div>

@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-1"></i> {{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@php
    $comp = $user->companies->first();
    $credit = $comp?->corporateCredit;
@endphp

<div class="row g-4">
    <!-- Left Column: Representative & Adjudication Controls -->
    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user-tie me-1"></i> Authorized Representative</h6>
                @if($user->kyc_aml_status === 'VERIFIED')
                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> VERIFIED</span>
                @elseif($user->kyc_aml_status === 'PENDING')
                    <span class="badge bg-warning text-dark">PENDING REVIEW</span>
                @else
                    <span class="badge bg-danger">{{ $user->kyc_aml_status ?? 'REJECTED' }}</span>
                @endif
            </div>
            <div class="card-body">
                <div class="mb-3 pb-2 border-bottom">
                    <div class="text-muted small">Representative Name</div>
                    <div class="fw-bold text-dark">{{ $user->name }}</div>
                </div>
                <div class="mb-3 pb-2 border-bottom">
                    <div class="text-muted small">Corporate Email</div>
                    <div class="fw-semibold text-dark">{{ $user->email }}</div>
                </div>
                <div class="mb-3 pb-2 border-bottom">
                    <div class="text-muted small">Phone / Contact</div>
                    <div class="fw-semibold font-monospace text-dark">{{ $user->phone ?: ($comp->pic_phone ?? 'Not provided') }}</div>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="text-muted small">Account Tier</div>
                        <div class="mt-1"><span class="badge bg-info text-dark">{{ str_replace('_', ' ', $user->user_type) }}</span></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Account Status</div>
                        <div class="mt-1"><span class="badge {{ $user->status === 'ACTIVE' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $user->status }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header py-3 bg-white">
                <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-stamp me-1 text-primary"></i> Compliance Adjudication</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Approving this KYC application verifies the corporate entity, sets the account tier to <strong>CORPORATE USER</strong>, and automatically provisions an initial B2B Corporate Credit facility.
                </p>
                <div class="d-flex gap-2">
                    @if($user->kyc_aml_status !== 'VERIFIED')
                        <form method="POST" action="{{ route('admin.kyc.approve', $user->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm fw-bold shadow-sm">
                                <i class="fas fa-check-circle me-1"></i> Approve &amp; Provision Credit
                            </button>
                        </form>
                    @endif
                    @if($user->kyc_aml_status !== 'REJECTED')
                        <form method="POST" action="{{ route('admin.kyc.reject', $user->id) }}"
                            onsubmit="return confirm('Reject Corporate KYC for {{ $user->name }}?');">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm fw-bold">
                                <i class="fas fa-times-circle me-1"></i> Reject KYC
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Corporate Entity Credentials & Credit Facility -->
    <div class="col-lg-7">
        <div class="card shadow-sm mb-4">
            <div class="card-header py-3 bg-white">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-building me-1"></i> Corporate Entity &amp; Trade Credentials</h6>
            </div>
            <div class="card-body">
                @if($comp)
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Legal Entity Name</div>
                            <div class="fw-bold text-dark fs-6">{{ $comp->company_name }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Industry Sector</div>
                            <div><span class="badge bg-light text-dark border">{{ $comp->industry ?? 'General Cargo & Logistics' }}</span></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">NIB (Business Registration No.)</div>
                            <div class="fw-bold font-monospace text-primary">{{ $comp->nib ?? 'Not submitted' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Corporate NPWP (Tax ID)</div>
                            <div class="fw-bold font-monospace text-primary">{{ $comp->npwp ?? 'Not submitted' }}</div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small">Registered Headquarters Address</div>
                            <div class="fw-semibold text-dark">{{ $comp->company_address ?? 'Not provided' }}</div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-folder-open fa-2x mb-2 text-gray-300 d-block"></i>
                        No corporate company profile submitted yet. Approving KYC will provision a default corporate entity record.
                    </div>
                @endif
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-landmark me-1"></i> B2B Corporate Credit Facility</h6>
                @if($credit)
                    <a href="{{ route('admin.credits.show', $credit->id) }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-sliders-h me-1"></i> Manage Credit Line
                    </a>
                @endif
            </div>
            <div class="card-body">
                @if($credit)
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <div class="text-muted small">Credit Limit</div>
                            <div class="fw-bold text-dark">IDR {{ number_format($credit->credit_limit_amount, 0, ',', '.') }}</div>
                        </div>
                        <div class="col-sm-4">
                            <div class="text-muted small">Used Credit</div>
                            <div class="fw-semibold text-muted">IDR {{ number_format($credit->used_credit_amount, 0, ',', '.') }}</div>
                        </div>
                        <div class="col-sm-4">
                            <div class="text-muted small">Terms of Payment</div>
                            <div><span class="badge bg-light text-dark border">{{ $credit->top_days }} Days TOP</span></div>
                        </div>
                    </div>
                @else
                    <div class="text-muted small">
                        No Corporate Credit facility provisioned yet. Click <strong>"Approve &amp; Provision Credit"</strong> to automatically create a credit facility for this company.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

