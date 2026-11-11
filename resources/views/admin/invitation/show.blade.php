@extends('layouts.dashboard')

@section('title', 'Review Operator Application')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="h3 mb-1 text-dark fw-bold">Operator Application Review</h1>
    <p class="mb-0 text-muted small">
      Verify hub readiness, assign station tier, and dispatch a 7-day WhatsApp onboarding invitation.
    </p>
  </div>
  <a href="{{ route('admin.invitations.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Back to Applications
  </a>
</div>

@if(session('warning'))
  <div class="alert alert-warning alert-dismissible fade show" role="alert">
    {{ session('warning') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if($errors->any())
  <div class="alert alert-danger" role="alert">
    <strong class="d-block mb-1">Please fix the highlighted fields:</strong>
    <ul class="mb-0 ps-3">
      @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

@php
  $statusBadge = match (true) {
      $invitation->status === 'PENDING_REVIEW' => ['class' => 'bg-warning text-dark', 'label' => 'PENDING REVIEW'],
      $invitation->status === 'INVITATION_SENT' && $invitation->isExpired() => ['class' => 'bg-secondary', 'label' => 'EXPIRED'],
      $invitation->status === 'INVITATION_SENT' => ['class' => 'bg-success', 'label' => 'INVITATION SENT'],
      $invitation->status === 'REGISTERED' => ['class' => 'bg-primary', 'label' => 'REGISTERED'],
      $invitation->status === 'EXPIRED' => ['class' => 'bg-secondary', 'label' => 'EXPIRED'],
      default => ['class' => 'bg-danger', 'label' => $invitation->status],
  };
  $portalBaseUrl = rtrim((string) config('app.portal_urls.operator', 'http://operator.fastcargo.local:8080'), '/');
  $registrationLink = $invitation->invitation_token ? $portalBaseUrl.'/register/'.$invitation->invitation_token : null;
@endphp

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold text-primary">
          <i class="fas fa-id-card me-1" aria-hidden="true"></i> Applicant Details
        </span>
        <span class="badge {{ $statusBadge['class'] }}">{{ $statusBadge['label'] }}</span>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4 text-muted small">Applicant Name</dt>
          <dd class="col-sm-8 fw-semibold text-dark">{{ $invitation->name }}</dd>

          <dt class="col-sm-4 text-muted small">Work Email</dt>
          <dd class="col-sm-8">{{ $invitation->email }}</dd>

          <dt class="col-sm-4 text-muted small">WhatsApp Number</dt>
          <dd class="col-sm-8">{{ $invitation->phone }}</dd>

          <dt class="col-sm-4 text-muted small">Proposed Hub / Station</dt>
          <dd class="col-sm-8">{{ $invitation->hub_name ?: '—' }}</dd>

          <dt class="col-sm-4 text-muted small">City</dt>
          <dd class="col-sm-8">{{ $invitation->city ?: '—' }}</dd>

          <dt class="col-sm-4 text-muted small">Assigned Hub</dt>
          <dd class="col-sm-8">
            @if($invitation->assignedHub)
              {{ $invitation->assignedHub->id }} — {{ $invitation->assignedHub->name }} ({{ $invitation->assignedHub->country_code }})
            @elseif($invitation->assigned_hub_id)
              {{ $invitation->assigned_hub_id }}
            @else
              <span class="text-muted">Default hub (IDJKT) unless assigned below</span>
            @endif
          </dd>

          <dt class="col-sm-4 text-muted small">Assigned Role Tier</dt>
          <dd class="col-sm-8">
            <span class="badge bg-light text-dark border">{{ $invitation->assigned_role_tier ?: 'OPERATOR_DOMESTIC' }}</span>
          </dd>

          <dt class="col-sm-4 text-muted small">Submitted At</dt>
          <dd class="col-sm-8">{{ $invitation->created_at?->format('d M Y H:i') ?? '—' }}</dd>

          <dt class="col-sm-4 text-muted small">Last Reviewed By</dt>
          <dd class="col-sm-8">{{ $invitation->invitedByAdmin?->name ?? '—' }}</dd>
        </dl>

        @if($invitation->rejection_reason)
          <div class="alert alert-danger mt-3 mb-0">
            <div class="fw-bold small mb-1">Rejection Reason</div>
            <p class="mb-0 small">{{ $invitation->rejection_reason }}</p>
          </div>
        @endif

        @if($registrationLink)
          <hr class="my-4">
          <h2 class="h6 fw-bold text-dark mb-2">Active Onboarding Credential</h2>
          <div class="p-3 rounded bg-light border">
            <div class="small mb-2 {{ $invitation->isExpired() ? 'text-danger fw-semibold' : 'text-success fw-semibold' }}">
              {{ $invitation->isExpired() ? 'Expired at:' : 'Expires at:' }}
              {{ $invitation->expires_at?->format('d M Y H:i') ?? 'No expiry recorded' }}
            </div>
            <label for="onboarding-link-preview" class="form-label small text-muted mb-1">Registration URL</label>
            <input id="onboarding-link-preview" type="text" readonly class="form-control form-control-sm font-monospace bg-white" value="{{ $registrationLink }}">
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-header bg-white py-3 fw-bold text-dark">
        <i class="fas fa-user-shield me-1" aria-hidden="true"></i> Governance Decision
      </div>
      <div class="card-body">
        @if($invitation->status === 'REGISTERED')
          <div class="alert alert-primary mb-0">
            <div class="fw-bold mb-1">Onboarding Complete</div>
            <p class="small mb-0">
              This applicant has completed operator registration and activated their station credentials. Invitation actions are locked.
            </p>
          </div>
        @else
          @if(in_array($invitation->status, ['INVITATION_SENT', 'EXPIRED'], true))
            <div class="mb-4 pb-4 border-bottom">
              <h2 class="h6 fw-bold text-dark mb-2">Renew Onboarding Token</h2>
              <p class="small text-muted mb-3">
                Rotates the single-use registration token for another 7 days and resends the WhatsApp onboarding notification.
              </p>
              <form method="POST" action="{{ route('admin.invitations.resend', $invitation->id) }}">
                @csrf
                <button type="submit" class="btn btn-primary w-100">
                  <i class="fas fa-sync-alt me-1" aria-hidden="true"></i> Renew Token &amp; Resend WhatsApp
                </button>
              </form>
            </div>
          @else
            <div class="mb-4 pb-4 border-bottom">
              <h2 class="h6 fw-bold text-success mb-3">Approve &amp; Issue Invitation</h2>
              <form method="POST" action="{{ route('admin.invitations.approve', $invitation->id) }}">
                @csrf
                <div class="mb-3">
                  <label for="assigned_hub_id" class="form-label small fw-semibold">Assign Operational Hub</label>
                  <select id="assigned_hub_id" name="assigned_hub_id" class="form-select">
                    <option value="">Default (IDJKT if unset at registration)</option>
                    @foreach($hubs as $hub)
                      <option value="{{ $hub->id }}" @selected(old('assigned_hub_id', $invitation->assigned_hub_id) === $hub->id)>
                        {{ $hub->id }} — {{ $hub->name }} ({{ $hub->city }})
                      </option>
                    @endforeach
                  </select>
                </div>

                <div class="mb-3">
                  <label for="assigned_role_tier" class="form-label small fw-semibold">Operator Role Tier</label>
                  @php
                    $selectedTier = old('assigned_role_tier', $invitation->status === 'PENDING_REVIEW' ? 'OPERATOR_INTERNATIONAL' : ($invitation->assigned_role_tier ?: 'OPERATOR_INTERNATIONAL'));
                  @endphp
                  <select id="assigned_role_tier" name="assigned_role_tier" class="form-select">
                    <option value="OPERATOR_INTERNATIONAL" @selected($selectedTier === 'OPERATOR_INTERNATIONAL')>
                      OPERATOR_INTERNATIONAL (Cross-Border)
                    </option>
                    <option value="OPERATOR_DOMESTIC" @selected($selectedTier === 'OPERATOR_DOMESTIC')>
                      OPERATOR_DOMESTIC (Domestic Hub)
                    </option>
                  </select>
                </div>

                <button type="submit" class="btn btn-success w-100 shadow-sm">
                  <i class="fas fa-check-circle me-1" aria-hidden="true"></i> Approve &amp; Send WhatsApp Invite
                </button>
              </form>
            </div>
          @endif

          <div>
            <h2 class="h6 fw-bold text-danger mb-2">Reject Application</h2>
            <p class="small text-muted mb-3">
              Marks this application as rejected and revokes any active onboarding token.
            </p>
            <form method="POST" action="{{ route('admin.invitations.reject', $invitation->id) }}">
              @csrf
              <div class="mb-3">
                <label for="rejection_reason" class="form-label small fw-semibold">Rejection Reason (optional)</label>
                <textarea
                  id="rejection_reason"
                  name="rejection_reason"
                  rows="3"
                  maxlength="500"
                  class="form-control"
                  placeholder="Provide reason for audit trail..."
                >{{ old('rejection_reason', $invitation->rejection_reason) }}</textarea>
              </div>
              <button type="submit" class="btn btn-outline-danger w-100">
                <i class="fas fa-times-circle me-1" aria-hidden="true"></i> Reject Application
              </button>
            </form>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

