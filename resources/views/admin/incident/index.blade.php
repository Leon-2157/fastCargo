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

<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white">
    <h6 class="m-0 font-weight-bold text-primary">Master Field Incident Queue</h6>
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
              <td class="ps-3 fw-bold text-primary">{{ $inc->incident_ticket_no }}</td>
              <td>
                <span class="badge bg-light text-dark border">{{ $inc->cargoBooking?->tracking_number }}</span>
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
              <td class="small text-muted" style="max-width: 250px;">{{ Str::limit($inc->admin_notes ?? 'No notes', 60) }}</td>
              <td>
                @if($inc->approval_status === 'APPROVED_BY_ADMIN')
                  <span class="badge bg-success">APPROVED</span>
                @elseif($inc->approval_status === 'PENDING_REVIEW')
                  <span class="badge bg-warning text-dark">PENDING REVIEW</span>
                @else
                  <span class="badge bg-danger">REJECTED</span>
                @endif
              </td>
              <td class="text-end pe-3">
                @if($inc->approval_status === 'PENDING_REVIEW')
                  <form method="POST" action="{{ route('admin.incidents.approve', $inc->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success me-1 shadow-sm">
                      <i class="fas fa-check me-1"></i> Approve
                    </button>
                  </form>
                  <form method="POST" action="{{ route('admin.incidents.reject', $inc->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger">
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
                No active incident escalations requiring review.
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

