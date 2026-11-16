@extends('layouts.dashboard')

@section('title', 'Network User Directory')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-dark mb-0 fw-bold">System User Directory</h3>
        <p class="text-muted small mb-0">Governance oversight across shippers, terminal operators, and compliance administrators.</p>
    </div>
</div>

@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-1"></i> {{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card shadow mb-4">
    <div class="card-header py-3 bg-white">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-md-auto">
                <h6 class="m-0 font-weight-bold text-primary">All Registered Users</h6>
            </div>
            <div class="col-md-auto d-flex flex-wrap gap-2 align-items-center">
                <form method="GET" action="{{ route('admin.users.index') }}" class="d-flex gap-1">
                    @if(request('role'))
                        <input type="hidden" name="role" value="{{ request('role') }}">
                    @endif
                    <label for="user_search" class="visually-hidden">Search users by name, email, or phone</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="user_search" name="search" value="{{ request('search') }}"
                            class="form-control" placeholder="Search name, email, phone...">
                        <button type="submit" class="btn btn-outline-primary" aria-label="Search users">
                            <i class="fas fa-search"></i>
                        </button>
                        @if(request('search'))
                            <a href="{{ route('admin.users.index', ['role' => request('role')]) }}" class="btn btn-outline-secondary" title="Clear search">
                                <i class="fas fa-times"></i>
                            </a>
                        @endif
                    </div>
                </form>
                <div class="d-flex flex-wrap gap-1">
                    <a href="{{ route('admin.users.index', ['search' => request('search')]) }}"
                        class="btn btn-sm {{ !request('role') || request('role') === 'ALL' ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
                    <a href="{{ route('admin.users.index', ['role' => 'RETAIL_CUSTOMER', 'search' => request('search')]) }}"
                        class="btn btn-sm {{ request('role') === 'RETAIL_CUSTOMER' ? 'btn-primary' : 'btn-outline-primary' }}">Retail</a>
                    <a href="{{ route('admin.users.index', ['role' => 'CORPORATE_USER', 'search' => request('search')]) }}"
                        class="btn btn-sm {{ in_array(request('role'), ['CORPORATE_USER', 'CORPORATE_CUSTOMER'], true) ? 'btn-primary' : 'btn-outline-primary' }}">Corporate</a>
                    <a href="{{ route('admin.users.index', ['role' => 'OPERATOR_USER', 'search' => request('search')]) }}"
                        class="btn btn-sm {{ request('role') === 'OPERATOR_USER' ? 'btn-warning text-dark' : 'btn-outline-warning' }}">Operator</a>
                    <a href="{{ route('admin.users.index', ['role' => 'ADMIN_USER', 'search' => request('search')]) }}"
                        class="btn btn-sm {{ request('role') === 'ADMIN_USER' ? 'btn-danger' : 'btn-outline-danger' }}">Admin</a>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">User Name</th>
                        <th>Email</th>
                        <th>Role / Classification</th>
                        <th>KYC / AML</th>
                        <th>2FA Security</th>
                        <th>Account Status</th>
                        <th>Joined</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php $currentAdminId = (string) auth('admin')->id(); @endphp
                    @forelse($users as $u)
                        @php $isSelf = (string) $u->id === $currentAdminId; @endphp
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('admin.users.show', $u->id) }}" class="fw-bold text-dark text-decoration-none">
                                    {{ $u->name }}
                                </a>
                                @if($isSelf)
                                    <span class="badge bg-light text-primary border ms-1">You</span>
                                @endif
                            </td>
                            <td>{{ $u->email }}</td>
                            <td>
                                @php
                                    $roleClass = match($u->user_type) {
                                        'ADMIN_USER' => 'bg-danger',
                                        'OPERATOR_USER' => 'bg-warning text-dark',
                                        'CORPORATE_USER', 'CORPORATE_CUSTOMER' => 'bg-info text-dark',
                                        default => 'bg-secondary'
                                    };
                                @endphp
                                <span class="badge {{ $roleClass }}">{{ str_replace('_', ' ', $u->user_type) }}</span>
                            </td>
                            <td>
                                @if($u->kyc_aml_status === 'VERIFIED')
                                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> VERIFIED</span>
                                @elseif($u->kyc_aml_status === 'PENDING')
                                    <span class="badge bg-warning text-dark">PENDING</span>
                                @else
                                    <span class="badge bg-light text-dark border">{{ $u->kyc_aml_status ?? 'UNVERIFIED' }}</span>
                                @endif
                            </td>
                            <td>
                                @if($u->hasTwoFactorEnabled())
                                    <span class="badge bg-primary" title="2FA enabled at {{ $u->two_factor_enabled_at?->format('d M Y H:i') }}">
                                        <i class="fas fa-shield-alt me-1"></i> ACTIVE
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border">DISABLED</span>
                                @endif
                            </td>
                            <td>
                                @if($u->status === 'ACTIVE')
                                    <span class="badge bg-success">ACTIVE</span>
                                @elseif($u->status === 'SUSPENDED')
                                    <span class="badge bg-warning text-dark">SUSPENDED</span>
                                @else
                                    <span class="badge bg-danger">{{ $u->status }}</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $u->created_at?->format('d M Y') ?? '—' }}</td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex gap-1 align-items-center">
                                    <a href="{{ route('admin.users.show', $u->id) }}" class="btn btn-sm btn-outline-primary" title="Inspect User Identity">
                                        <i class="fas fa-eye me-1"></i> Inspect
                                    </a>
                                    @if($u->hasTwoFactorEnabled())
                                        <form method="POST" action="{{ route('admin.users.reset-2fa', $u->id) }}" class="d-inline"
                                            onsubmit="return confirm('Reset 2FA for {{ $u->email }}? The user will be required to scan a new authenticator QR upon their next login.');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Reset 2FA (Lost Device)" aria-label="Reset 2FA for {{ $u->email }}">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if(!$isSelf)
                                        @if($u->status === 'ACTIVE')
                                            <form method="POST" action="{{ route('admin.users.suspend', $u->id) }}" class="d-inline"
                                                onsubmit="return confirm('Suspend user access for {{ $u->email }}?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Suspend User" aria-label="Suspend {{ $u->email }}">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                            </form>
                                        @elseif($u->status === 'SUSPENDED' || $u->status === 'BLACKLISTED')
                                            <form method="POST" action="{{ route('admin.users.restore', $u->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Restore User" aria-label="Restore {{ $u->email }}">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-users-slash fa-2x mb-2 text-gray-300 d-block"></i>
                                No matching users found for the current filter.
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
