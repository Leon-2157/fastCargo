@extends('layouts.dashboard')

@section('title', 'Network User Directory')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">System User Directory</h3>
    <p class="text-muted small mb-0">Governance oversight across shippers, terminal operators, and compliance administrators.</p>
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

@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-ban me-1"></i> {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
    <h6 class="m-0 font-weight-bold text-primary">All Registered Users</h6>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.users.index') }}" class="btn btn-sm {{ !request('role') || request('role') === 'ALL' ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
      <a href="{{ route('admin.users.index', ['role' => 'RETAIL_CUSTOMER']) }}" class="btn btn-sm {{ request('role') === 'RETAIL_CUSTOMER' ? 'btn-primary' : 'btn-outline-primary' }}">Retail</a>
      <a href="{{ route('admin.users.index', ['role' => 'CORPORATE_CUSTOMER']) }}" class="btn btn-sm {{ request('role') === 'CORPORATE_CUSTOMER' ? 'btn-primary' : 'btn-outline-primary' }}">Corporate</a>
      <a href="{{ route('admin.users.index', ['role' => 'OPERATOR_USER']) }}" class="btn btn-sm {{ request('role') === 'OPERATOR_USER' ? 'btn-warning text-dark' : 'btn-outline-warning' }}">Operator</a>
      <a href="{{ route('admin.users.index', ['role' => 'ADMIN_USER']) }}" class="btn btn-sm {{ request('role') === 'ADMIN_USER' ? 'btn-danger' : 'btn-outline-danger' }}">Admin</a>
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
            <th>Account Status</th>
            <th>Joined</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($users as $u)
            <tr>
              <td class="ps-3 fw-bold text-dark">{{ $u->name }}</td>
              <td>{{ $u->email }}</td>
              <td>
                @php
                  $roleClass = match($u->user_type) {
                    'ADMIN_USER' => 'bg-danger',
                    'OPERATOR_USER' => 'bg-warning text-dark',
                    'CORPORATE_CUSTOMER' => 'bg-info',
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
                @if($u->status === 'ACTIVE')
                  <span class="badge bg-success">ACTIVE</span>
                @elseif($u->status === 'SUSPENDED')
                  <span class="badge bg-warning text-dark">SUSPENDED</span>
                @else
                  <span class="badge bg-danger">{{ $u->status }}</span>
                @endif
              </td>
              <td class="small text-muted">{{ $u->created_at->format('d M Y') }}</td>
              <td class="text-end pe-3">
                @if($u->status === 'ACTIVE')
                  <form method="POST" action="{{ route('admin.users.suspend', $u->id) }}" class="d-inline" onsubmit="return confirm('Suspend user access?');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Suspend User">
                      <i class="fas fa-pause"></i>
                    </button>
                  </form>
                @elseif($u->status === 'SUSPENDED')
                  <form method="POST" action="{{ route('admin.users.restore', $u->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-success" title="Restore User">
                      <i class="fas fa-play"></i>
                    </button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
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

