@extends('layouts.dashboard')

@section('title', 'Field Incidents & Escalations')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Field Incidents &amp; Operational Escalations</h3>
    <p class="text-muted small mb-0">Record damage claims, weather holds, port delays, and request emergency supervisor funds.</p>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white">
    <h6 class="m-0 font-weight-bold text-primary">Escalated Incident Tickets</h6>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Ticket #</th>
            <th>Booking Resi</th>
            <th>Incident Category</th>
            <th>Requested Cost</th>
            <th>Reported By</th>
            <th>Approval Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          @forelse($incidents as $inc)
            <tr>
              <td class="ps-3 fw-bold text-primary">{{ $inc->incident_ticket_no }}</td>
              <td>
                <a href="{{ route('operator.bookings.show', $inc->cargoBooking->id) }}" class="text-decoration-none fw-semibold">
                  {{ $inc->cargoBooking->tracking_number }}
                </a>
              </td>
              <td><span class="badge bg-danger">{{ str_replace('_', ' ', $inc->incident_type) }}</span></td>
              <td>
                @if($inc->requested_amount > 0)
                  IDR {{ number_format($inc->requested_amount, 0, ',', '.') }}
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="small">{{ $inc->reporter?->user?->name ?? 'Operator' }}</td>
              <td>
                @php
                  $badgeClass = match($inc->approval_status) {
                    'PENDING_REVIEW' => 'bg-warning text-dark',
                    'APPROVED_BY_ADMIN' => 'bg-success',
                    'REJECTED' => 'bg-danger',
                    default => 'bg-secondary'
                  };
                @endphp
                <span class="badge {{ $badgeClass }} px-2 py-1">{{ str_replace('_', ' ', $inc->approval_status) }}</span>
              </td>
              <td class="small text-muted">{{ $inc->created_at->format('d M Y, H:i') }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">
                <i class="fas fa-shield-alt fa-3x mb-3 text-gray-300 d-block"></i>
                No active operational incidents recorded. All terminal operations are running within nominal parameters.
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

