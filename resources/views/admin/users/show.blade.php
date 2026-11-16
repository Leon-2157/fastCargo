@extends('layouts.dashboard')

@section('title', 'User Identity & Governance — ' . $user->name)

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="fas fa-arrow-left me-1"></i> Back to User Directory
        </a>
        <h3 class="text-dark mb-0 fw-bold">User Identity &amp; Access Governance</h3>
        <p class="text-muted small mb-0">Inspect security posture, linked organizations, and immutable audit history for <strong>{{ $user->email }}</strong>.</p>
    </div>
</div>

@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-1"></i> {{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@php
    $isSelf = (string) $user->id === (string) auth('admin')->id();
    $roleClass = match($user->user_type) {
        'ADMIN_USER' => 'bg-danger',
        'OPERATOR_USER' => 'bg-warning text-dark',
        'CORPORATE_USER', 'CORPORATE_CUSTOMER' => 'bg-info text-dark',
        default => 'bg-secondary'
    };
@endphp

<div class="row g-4">
    <!-- Left Column: Identity Overview & Governance Controls -->
    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-id-card me-1"></i> Identity Profile</h6>
                <span class="badge {{ $roleClass }}">{{ str_replace('_', ' ', $user->user_type) }}</span>
            </div>
            <div class="card-body">
                <div class="mb-3 pb-2 border-bottom">
                    <div class="text-muted small">Full Name</div>
                    <div class="fw-bold text-dark fs-6">
                        {{ $user->name }}
                        @if($isSelf)
                            <span class="badge bg-light text-primary border ms-1">Current Session</span>
                        @endif
                    </div>
                </div>
                <div class="mb-3 pb-2 border-bottom">
                    <div class="text-muted small">Email Address</div>
                    <div class="fw-semibold text-dark">{{ $user->email }}</div>
                </div>
                <div class="mb-3 pb-2 border-bottom">
                    <div class="text-muted small">Phone / WhatsApp</div>
                    <div class="fw-semibold text-dark font-monospace">{{ $user->phone ?: 'Not provided' }}</div>
                </div>
                <div class="row g-2 mb-3 pb-2 border-bottom">
                    <div class="col-6">
                        <div class="text-muted small">Account Status</div>
                        <div class="mt-1">
                            @if($user->status === 'ACTIVE')
                                <span class="badge bg-success">ACTIVE</span>
                            @elseif($user->status === 'SUSPENDED')
                                <span class="badge bg-warning text-dark">SUSPENDED</span>
                            @else
                                <span class="badge bg-danger">{{ $user->status }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">KYC / AML Status</div>
                        <div class="mt-1">
                            @if($user->kyc_aml_status === 'VERIFIED')
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> VERIFIED</span>
                            @elseif($user->kyc_aml_status === 'PENDING')
                                <span class="badge bg-warning text-dark">PENDING</span>
                            @else
                                <span class="badge bg-secondary">{{ $user->kyc_aml_status ?? 'UNVERIFIED' }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="text-muted small">2FA Authenticator</div>
                        <div class="mt-1">
                            @if($user->hasTwoFactorEnabled())
                                <span class="badge bg-primary"><i class="fas fa-shield-alt me-1"></i> ENABLED</span>
                            @else
                                <span class="badge bg-light text-muted border">NOT CONFIGURED</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Registered On</div>
                        <div class="small fw-semibold text-dark mt-1">{{ $user->created_at?->format('d M Y, H:i') ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Governance Actions Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header py-3 bg-white">
                <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-gavel me-1 text-danger"></i> Security &amp; Access Controls</h6>
            </div>
            <div class="card-body">
                @if($isSelf)
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        <i class="fas fa-info-circle me-1"></i> Self-suspension and blacklisting are disabled for your own active administrator session.
                    </div>
                @else
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @if($user->status === 'ACTIVE')
                            <form method="POST" action="{{ route('admin.users.suspend', $user->id) }}"
                                onsubmit="return confirm('Suspend account access for {{ $user->email }}?');">
                                @csrf
                                <button type="submit" class="btn btn-warning btn-sm fw-bold">
                                    <i class="fas fa-pause me-1"></i> Suspend Account
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.users.restore', $user->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm fw-bold">
                                    <i class="fas fa-play me-1"></i> Restore to Active
                                </button>
                            </form>
                        @endif

                        @if($user->status !== 'BLACKLISTED')
                            <form method="POST" action="{{ route('admin.users.block', $user->id) }}"
                                onsubmit="return confirm('Permanently blacklist {{ $user->email }}?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm fw-bold">
                                    <i class="fas fa-ban me-1"></i> Blacklist Account
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                @if($user->hasTwoFactorEnabled())
                    <div class="pt-2 border-top">
                        <form method="POST" action="{{ route('admin.users.reset-2fa', $user->id) }}"
                            onsubmit="return confirm('Reset Two-Factor Authentication for {{ $user->email }}?');">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="fas fa-key me-1 text-danger"></i> Reset 2FA Authenticator Secret
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Right Column: Linked Organization / Operator Profile & Audit Logs -->
    <div class="col-lg-7">
        @if($user->companies && $user->companies->isNotEmpty())
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-building me-1"></i> Linked Corporate Entities</h6>
                    <a href="{{ route('admin.kyc.show', $user->id) }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-file-contract me-1"></i> Open KYC File
                    </a>
                </div>
                <div class="card-body">
                    @foreach($user->companies as $comp)
                        <div class="border rounded p-3 {{ !$loop->last ? 'mb-3' : '' }} bg-light">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="fw-bold text-dark mb-0">{{ $comp->company_name }}</h6>
                                    <small class="text-muted">{{ $comp->industry ?? 'Logistics & Trade' }}</small>
                                </div>
                                @if($comp->corporateCredit)
                                    <a href="{{ route('admin.credits.show', $comp->corporateCredit->id) }}" class="badge bg-primary text-decoration-none">
                                        Credit Limit: IDR {{ number_format($comp->corporateCredit->credit_limit_amount, 0, ',', '.') }}
                                    </a>
                                @endif
                            </div>
                            <div class="row g-2 small">
                                <div class="col-sm-6">
                                    <span class="text-muted">NIB:</span> <code>{{ $comp->nib ?? 'N/A' }}</code>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted">NPWP:</span> <code>{{ $comp->npwp ?? 'N/A' }}</code>
                                </div>
                                <div class="col-12">
                                    <span class="text-muted">Address:</span> {{ $comp->company_address ?? 'N/A' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($user->operatorProfile)
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 bg-white">
                    <h6 class="m-0 font-weight-bold text-warning-emphasis"><i class="fas fa-anchor me-1"></i> Operator Terminal Assignment</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 small">
                        <div class="col-sm-6">
                            <div class="text-muted">Employee Badge ID</div>
                            <div class="fw-bold font-monospace">{{ $user->operatorProfile->employee_id ?? 'N/A' }}</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="text-muted">Assigned Port Hub</div>
                            <div class="fw-bold">{{ $user->operatorProfile->assignedPort?->name ?? $user->operatorProfile->assigned_port_id ?? 'Unassigned' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Recent Audit Trail for User -->
        <div class="card shadow-sm mb-4">
            <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-shield-alt me-1"></i> Recent Security &amp; Governance Logs</h6>
                <a href="{{ route('admin.audit.index', ['search' => $user->email]) }}" class="btn btn-sm btn-link text-decoration-none p-0">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Action</th>
                                <th>Target</th>
                                <th>IP Address</th>
                                <th class="pe-3">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($auditTrails as $log)
                                <tr>
                                    <td class="ps-3"><span class="badge bg-primary">{{ $log->action }}</span></td>
                                    <td class="font-monospace text-muted">{{ $log->target_type }}:{{ Str::limit((string) $log->target_id, 14) }}</td>
                                    <td><code>{{ $log->ip_address ?? '127.0.0.1' }}</code></td>
                                    <td class="pe-3 text-muted">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, H:i:s') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No recent audit events recorded for this user.</td>
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

