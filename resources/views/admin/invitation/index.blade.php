@extends('layouts.dashboard')

@section('title', 'Operator Applications & Invitations')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Operator Network Applications</h3>
    <p class="text-muted small mb-0">Review inbound station operator applications from the public portal and issue registration tokens.</p>
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

<!-- Quick Metric Filters -->
<div class="row mb-4">
  <div class="col-md-3 col-6 mb-2">
    <a href="{{ route('admin.invitations.index') }}" class="card text-decoration-none shadow-sm {{ !request('status') || request('status') === 'ALL' ? 'border-primary bg-light' : '' }}">
      <div class="card-body py-2">
        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Submissions</div>
        <div class="h5 mb-0 fw-bold text-gray-800">{{ $invitations->total() }}</div>
      </div>
    </a>
  </div>
  <div class="col-md-3 col-6 mb-2">
    <a href="{{ route('admin.invitations.index', ['status' => 'PENDING_REVIEW']) }}" class="card text-decoration-none shadow-sm {{ request('status') === 'PENDING_REVIEW' ? 'border-warning bg-light' : '' }}">
      <div class="card-body py-2">
        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending Compliance Review</div>
        <div class="h5 mb-0 fw-bold text-warning">{{ $stats['pending'] }}</div>
      </div>
    </a>
  </div>
  <div class="col-md-3 col-6 mb-2">
    <a href="{{ route('admin.invitations.index', ['status' => 'INVITATION_SENT']) }}" class="card text-decoration-none shadow-sm {{ request('status') === 'INVITATION_SENT' ? 'border-success bg-light' : '' }}">
      <div class="card-body py-2">
        <div class="text-xs fw-bold text-success text-uppercase mb-1">Invitation Sent</div>
        <div class="h5 mb-0 fw-bold text-success">{{ $stats['invited'] }}</div>
      </div>
    </a>
  </div>
  <div class="col-md-3 col-6 mb-2">
    <a href="{{ route('admin.invitations.index', ['status' => 'REJECTED']) }}" class="card text-decoration-none shadow-sm {{ request('status') === 'REJECTED' ? 'border-danger bg-light' : '' }}">
      <div class="card-body py-2">
        <div class="text-xs fw-bold text-danger text-uppercase mb-1">Rejected</div>
        <div class="h5 mb-0 fw-bold text-danger">{{ $stats['rejected'] }}</div>
      </div>
    </a>
  </div>
</div>

<!-- Table Card -->
<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white">
    <h6 class="m-0 font-weight-bold text-primary">Inbound Candidate Roster</h6>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Applicant Name</th>
            <th>Email &amp; Phone</th>
            <th>Proposed Hub / Station</th>
            <th>City</th>
            <th>Status</th>
            <th>Token Expiration</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($invitations as $inv)
            <tr>
              <td class="ps-3 fw-bold text-dark">{{ $inv->name }}</td>
              <td>
                <div>{{ $inv->email }}</div>
                <small class="text-muted">{{ $inv->phone }}</small>
              </td>
              <td><span class="badge bg-light text-dark border">{{ $inv->hub_name }}</span></td>
              <td>{{ $inv->city }}</td>
              <td>
                @if($inv->status === 'PENDING_REVIEW')
                  <span class="badge bg-warning text-dark">PENDING REVIEW</span>
                @elseif($inv->status === 'INVITATION_SENT')
                  @if($inv->isExpired())
                    <span class="badge bg-secondary">EXPIRED</span>
                  @else
                    <span class="badge bg-success">INVITATION SENT</span>
                  @endif
                @elseif($inv->status === 'REGISTERED')
                  <span class="badge bg-primary">REGISTERED</span>
                @elseif($inv->status === 'EXPIRED')
                  <span class="badge bg-secondary">EXPIRED</span>
                @else
                  <span class="badge bg-danger">{{ $inv->status }}</span>
                @endif
              </td>
              <td>
                @if($inv->invitation_token)
                  @if($inv->expires_at)
                    <div class="small fw-semibold {{ $inv->isExpired() ? 'text-danger' : 'text-success' }}">
                      {{ $inv->isExpired() ? 'Expired:' : 'Expires:' }} {{ $inv->expires_at->format('d M Y') }}
                    </div>
                  @endif
                  <div class="text-xs text-muted text-truncate" style="max-width: 140px;">
                    <code>{{ $inv->invitation_token }}</code>
                  </div>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="text-end pe-3">
                <a href="{{ route('admin.invitations.show', $inv->id) }}" class="btn btn-sm btn-outline-secondary me-1" title="Review Application" aria-label="Review application for {{ $inv->name }}">
                  <i class="fas fa-eye" aria-hidden="true"></i>
                </a>
                @if($inv->status === 'PENDING_REVIEW')
                  <form method="POST" action="{{ route('admin.invitations.approve', $inv->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success me-1 shadow-sm">
                      <i class="fas fa-check me-1" aria-hidden="true"></i> Approve
                    </button>
                  </form>
                  <form method="POST" action="{{ route('admin.invitations.reject', $inv->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject Application" aria-label="Reject application for {{ $inv->name }}">
                      <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                  </form>
                @elseif(in_array($inv->status, ['INVITATION_SENT', 'EXPIRED'], true))
                  <form method="POST" action="{{ route('admin.invitations.resend', $inv->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Renew Token +7 Days">
                      <i class="fas fa-sync-alt me-1" aria-hidden="true"></i> Renew Token
                    </button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">
                <i class="fas fa-inbox fa-3x mb-3 text-gray-300 d-block"></i>
                No operator applications currently pending.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if($invitations->hasPages())
    <div class="card-footer bg-white py-3">
      {{ $invitations->links() }}
    </div>
  @endif
</div>
@endsection

