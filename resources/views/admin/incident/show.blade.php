@extends('layouts.dashboard')

@section('title', 'Incident Escalation Dossier — ' . $incident->incident_ticket_no)

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('admin.incidents.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
      <i class="fas fa-arrow-left me-1"></i> Back to Field Incident Queue
    </a>
    <h3 class="text-dark mb-0 fw-bold">Incident Escalation: {{ $incident->incident_ticket_no }}</h3>
    <p class="text-muted small mb-0">Field report inspection, BAP evidence review, and executive fund/waiver adjudication.</p>
  </div>
  <div>
    @if($incident->approval_status === 'APPROVED_BY_ADMIN')
      <span class="badge bg-success px-3 py-2 fs-6"><i class="fas fa-check-circle me-1"></i> APPROVED BY ADMIN</span>
    @elseif(in_array($incident->approval_status, ['PENDING', 'PENDING_REVIEW'], true))
      <span class="badge bg-warning text-dark px-3 py-2 fs-6"><i class="fas fa-clock me-1"></i> PENDING ADJUDICATION</span>
    @else
      <span class="badge bg-danger px-3 py-2 fs-6"><i class="fas fa-times-circle me-1"></i> REJECTED</span>
    @endif
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

<div class="row g-4">
  <!-- Left Column: Ticket Metadata & Consignment Context -->
  <div class="col-lg-6">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-exclamation-triangle me-2"></i>Escalation Ticket Details</h6>
      </div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <div class="p-3 bg-light rounded border">
              <div class="text-xs text-muted text-uppercase fw-bold">Incident Category</div>
              <div class="fw-bold text-danger mt-1">{{ str_replace('_', ' ', $incident->incident_type) }}</div>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="p-3 bg-light rounded border">
              <div class="text-xs text-muted text-uppercase fw-bold">Requested Emergency Cost</div>
              <div class="fw-bold text-dark mt-1">
                @if($incident->requested_amount > 0)
                  IDR {{ number_format($incident->requested_amount, 0, ',', '.') }}
                @else
                  No Financial Disbursement
                @endif
              </div>
            </div>
          </div>
        </div>

        <dl class="row mb-0 small">
          <dt class="col-sm-4 text-muted">Ticket Reference</dt>
          <dd class="col-sm-8 fw-bold text-primary"><code>{{ $incident->incident_ticket_no }}</code></dd>

          <dt class="col-sm-4 text-muted">Reporting Officer</dt>
          <dd class="col-sm-8">
            <div class="fw-semibold text-dark">{{ $incident->reporter?->user?->name ?? 'Terminal Field Officer' }}</div>
            <div class="text-muted">{{ $incident->reporter?->user?->email ?? '—' }}</div>
          </dd>

          <dt class="col-sm-4 text-muted">SLA Response Target</dt>
          <dd class="col-sm-8">{{ $incident->sla_response_minutes ?? 60 }} Minutes</dd>

          <dt class="col-sm-4 text-muted">BAP / Evidence Ref</dt>
          <dd class="col-sm-8">
            @if($incident->bap_document_path)
              <code>{{ $incident->bap_document_path }}</code>
            @else
              <span class="text-muted">Digital field log attached in notes</span>
            @endif
          </dd>

          <dt class="col-sm-4 text-muted">Escalated At</dt>
          <dd class="col-sm-8 mb-0">{{ $incident->created_at?->format('d M Y, H:i T') ?? '—' }}</dd>
        </dl>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-box-open me-2"></i>Affected Consignment Waybill</h6>
      </div>
      <div class="card-body">
        @if($incident->cargoBooking)
          <dl class="row mb-0 small">
            <dt class="col-sm-4 text-muted">Waybill Resi</dt>
            <dd class="col-sm-8 fw-bold text-dark"><code>{{ $incident->cargoBooking->tracking_number }}</code></dd>

            <dt class="col-sm-4 text-muted">Maritime Corridor</dt>
            <dd class="col-sm-8 fw-semibold">
              {{ $incident->cargoBooking->route?->originPort?->port_name ?? 'Origin' }}
              <i class="fas fa-arrow-right mx-1 text-primary"></i>
              {{ $incident->cargoBooking->route?->destinationPort?->port_name ?? 'Destination' }}
            </dd>

            <dt class="col-sm-4 text-muted">Cargo Status</dt>
            <dd class="col-sm-8"><span class="badge bg-info">{{ $incident->cargoBooking->cargo_status }}</span></dd>

            <dt class="col-sm-4 text-muted">Shipper Account</dt>
            <dd class="col-sm-8 mb-0">{{ $incident->cargoBooking->shipper?->name ?? '—' }}</dd>
          </dl>
        @else
          <p class="text-muted small mb-0">No specific cargo booking linked to this escalation ticket.</p>
        @endif
      </div>
    </div>
  </div>

  <!-- Right Column: Audit Notes & Executive Adjudication Form -->
  <div class="col-lg-6">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-clipboard-list me-2"></i>Incident Log &amp; Resolution Notes</h6>
      </div>
      <div class="card-body">
        <pre class="bg-light p-3 rounded border small mb-0" style="white-space: pre-wrap; font-family: inherit;">{{ $incident->admin_notes ?: 'No field or administrative notes recorded yet.' }}</pre>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-gavel me-2"></i>Executive Governance Decision</h6>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin.incidents.approve', $incident->id) }}" id="incidentAdjudicationForm">
          @csrf
          <div class="mb-3">
            <label for="incidentAdminNotes" class="form-label small fw-bold text-dark">Resolution Notes / Audit Rationale</label>
            <textarea
              id="incidentAdminNotes"
              name="admin_notes"
              rows="3"
              class="form-control form-control-sm"
              placeholder="Document the reason for approving emergency funds or rejecting this claim..."
            ></textarea>
            <div class="form-text small">Appended with timestamp and your administrator identity into the immutable audit log.</div>
          </div>

          <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-success btn-sm fw-bold px-3 shadow-sm">
              <i class="fas fa-check me-1"></i> Approve &amp; Authorize
            </button>
            <button
              type="submit"
              formaction="{{ route('admin.incidents.reject', $incident->id) }}"
              class="btn btn-outline-danger btn-sm fw-bold px-3"
            >
              <i class="fas fa-times me-1"></i> Reject Claim
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

