@extends('layouts.dashboard')

@section('title', 'Operator Incident Review')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Operator Incident &amp; Escalation Review</h3>
    <p class="text-muted small mb-0">Adjudicate emergency funds, weather delays, and damage claims from terminal operators.</p>
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

@if(isset($stats))
<div class="row mb-4">
  <div class="col-md-4 mb-2">
    <div class="card shadow-sm border-start-warning">
      <div class="card-body py-3">
        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending Adjudication</div>
        <div class="h5 mb-0 fw-bold text-gray-800">{{ number_format($stats['pending'] ?? 0) }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card shadow-sm border-start-success">
      <div class="card-body py-3">
        <div class="text-xs fw-bold text-success text-uppercase mb-1">Approved Escalations</div>
        <div class="h5 mb-0 fw-bold text-success">{{ number_format($stats['approved'] ?? 0) }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card shadow-sm border-start-danger">
      <div class="card-body py-3">
        <div class="text-xs fw-bold text-danger text-uppercase mb-1">Rejected Claims</div>
        <div class="h5 mb-0 fw-bold text-danger">{{ number_format($stats['rejected'] ?? 0) }}</div>
      </div>
    </div>
  </div>
</div>
@endif

<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h6 class="m-0 font-weight-bold text-primary">Master Field Incident Queue</h6>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <form method="GET" action="{{ route('admin.incidents.index') }}" class="d-flex gap-2">
        @if(request('status'))
          <input type="hidden" name="status" value="{{ request('status') }}">
        @endif
        <div class="input-group input-group-sm" style="min-width: 230px;">
          <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
          <input type="text" name="search" class="form-control" placeholder="Ticket #, category, or resi..." aria-label="Search incidents" value="{{ request('search') }}">
          @if(request('search'))
            <a href="{{ route('admin.incidents.index', ['status' => request('status')]) }}" class="btn btn-outline-secondary" aria-label="Clear incident search"><i class="fas fa-times"></i></a>
          @endif
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
      </form>
      <div class="btn-group btn-group-sm" role="group" aria-label="Incident status filter">
        <a href="{{ route('admin.incidents.index', ['search' => request('search')]) }}" class="btn {{ !request('status') || request('status') === 'ALL' ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
        <a href="{{ route('admin.incidents.index', ['status' => 'PENDING', 'search' => request('search')]) }}" class="btn {{ in_array(request('status'), ['PENDING', 'PENDING_REVIEW'], true) ? 'btn-warning text-dark' : 'btn-outline-warning' }}">Pending</a>
        <a href="{{ route('admin.incidents.index', ['status' => 'APPROVED_BY_ADMIN', 'search' => request('search')]) }}" class="btn {{ request('status') === 'APPROVED_BY_ADMIN' ? 'btn-success' : 'btn-outline-success' }}">Approved</a>
        <a href="{{ route('admin.incidents.index', ['status' => 'REJECTED', 'search' => request('search')]) }}" class="btn {{ request('status') === 'REJECTED' ? 'btn-danger' : 'btn-outline-danger' }}">Rejected</a>
      </div>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Ticket #</th>
            <th>Booking Resi</th>
            <th>Category</th>
            <th>Requested Cost</th>
            <th>Reporting Officer</th>
            <th>Notes / Rationale</th>
            <th>Status</th>
            <th class="text-end pe-3">Governance Decision</th>
          </tr>
        </thead>
        <tbody>
          @forelse($incidents as $inc)
            <tr>
              <td class="ps-3 fw-bold text-primary">
                <a href="{{ route('admin.incidents.show', $inc->id) }}" class="text-decoration-none fw-bold">{{ $inc->incident_ticket_no }}</a>
              </td>
              <td>
                <span class="badge bg-light text-dark border">{{ $inc->cargoBooking?->tracking_number ?? '—' }}</span>
              </td>
              <td><span class="badge bg-danger">{{ str_replace('_', ' ', $inc->incident_type) }}</span></td>
              <td class="fw-bold">
                @if($inc->requested_amount > 0)
                  IDR {{ number_format($inc->requested_amount, 0, ',', '.') }}
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="small">{{ $inc->reporter?->user?->name ?? 'Terminal Officer' }}</td>
              <td class="small text-muted" style="max-width: 240px;">{{ Str::limit($inc->admin_notes ?? 'No notes', 60) }}</td>
              <td>
                @if($inc->approval_status === 'APPROVED_BY_ADMIN')
                  <span class="badge bg-success">APPROVED</span>
                @elseif(in_array($inc->approval_status, ['PENDING', 'PENDING_REVIEW'], true))
                  <span class="badge bg-warning text-dark">PENDING REVIEW</span>
                @else
                  <span class="badge bg-danger">REJECTED</span>
                @endif
              </td>
              <td class="text-end pe-3">
                <a href="{{ route('admin.incidents.show', $inc->id) }}" class="btn btn-sm btn-outline-primary me-1">
                  <i class="fas fa-eye me-1"></i> Inspect
                </a>
                @if(in_array($inc->approval_status, ['PENDING', 'PENDING_REVIEW'], true))
                  <form method="POST" action="{{ route('admin.incidents.approve', $inc->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success me-1 shadow-sm" aria-label="Approve incident {{ $inc->incident_ticket_no }}">
                      <i class="fas fa-check me-1"></i> Approve
                    </button>
                  </form>
                  <form method="POST" action="{{ route('admin.incidents.reject', $inc->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Reject incident {{ $inc->incident_ticket_no }}">
                      <i class="fas fa-times"></i>
                    </button>
                  </form>
                @else
                  <span class="text-muted small">Adjudicated</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center py-5 text-muted">
                <i class="fas fa-shield-alt fa-2x mb-2 text-gray-300 d-block"></i>
                No field incident escalations match the current filter criteria.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if($incidents->hasPages())
    <div class="card-footer bg-white py-3">
      {{ $incidents->links() }}
    </div>
  @endif
</div>
@endsection
