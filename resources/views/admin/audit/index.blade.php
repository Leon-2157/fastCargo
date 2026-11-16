@extends('layouts.dashboard')

@section('title', 'Immutable Governance Audit Trail')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Immutable Governance Audit Trail</h3>
    <p class="text-muted small mb-0">Cryptographically verifiable, non-repudiable audit logs across all financial, operational and security events.</p>
  </div>
  <div>
    <span class="badge bg-success px-3 py-2"><i class="fas fa-lock me-1"></i> Append-Only Immutability Active</span>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h6 class="m-0 font-weight-bold text-primary">System Transaction &amp; Action Ledger</h6>
    <form method="GET" action="{{ route('admin.audit.index') }}" class="d-flex flex-wrap align-items-center gap-2">
      <div class="input-group input-group-sm" style="min-width: 220px;">
        <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
        <input
          type="text"
          name="search"
          class="form-control"
          placeholder="Actor, action, target, or IP..."
          aria-label="Search audit trail"
          value="{{ request('search') }}"
        >
      </div>
      <select name="action" class="form-select form-select-sm" style="width: auto; min-width: 190px;" aria-label="Filter by audit action">
        <option value="ALL">All Action Codes</option>
        @foreach($availableActions ?? [] as $act)
          <option value="{{ $act }}" {{ request('action') === $act ? 'selected' : '' }}>{{ $act }}</option>
        @endforeach
      </select>
      <button type="submit" class="btn btn-sm btn-primary">Filter</button>
      @if(request('search') || (request('action') && request('action') !== 'ALL'))
        <a href="{{ route('admin.audit.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
      @endif
    </form>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 font-monospace" style="font-size: 12px;">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Log ID</th>
            <th>Actor Identity</th>
            <th>Action Code</th>
            <th>Target Resource</th>
            <th>State Diff (Before / After)</th>
            <th>Client IP Address</th>
            <th class="pe-3">Logged At</th>
          </tr>
        </thead>
        <tbody>
          @forelse($trails as $log)
            <tr>
              <td class="ps-3 fw-bold text-muted">#{{ $log->id }}</td>
              <td>
                <span class="fw-bold text-dark">{{ $log->actor_name ?? 'System Automated' }}</span>
                <div class="text-xs text-muted">{{ $log->actor_email ?? 'system.internal' }}</div>
              </td>
              <td><span class="badge bg-primary">{{ $log->action }}</span></td>
              <td>
                <span class="badge bg-light text-dark border">{{ $log->target_type ?? 'GLOBAL' }}: {{ Str::limit((string) ($log->target_id ?? 'ALL'), 18) }}</span>
              </td>
              <td>
                @if(!empty($log->before) || !empty($log->after))
                  <button
                    class="btn btn-xs btn-outline-secondary py-0 px-2"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#auditDiff{{ $log->id }}"
                    aria-expanded="false"
                    aria-controls="auditDiff{{ $log->id }}"
                  >
                    <i class="fas fa-code me-1"></i> Inspect Diff
                  </button>
                  <div class="collapse mt-2" id="auditDiff{{ $log->id }}">
                    <div class="p-2 bg-light border rounded" style="max-width: 420px; white-space: pre-wrap;">
                      @if(!empty($log->before))
                        <div class="text-danger fw-bold mb-1">Before:</div>
                        <div class="mb-2 text-muted">{{ is_string($log->before) ? $log->before : json_encode($log->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
                      @endif
                      @if(!empty($log->after))
                        <div class="text-success fw-bold mb-1">After:</div>
                        <div class="text-dark">{{ is_string($log->after) ? $log->after : json_encode($log->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
                      @endif
                    </div>
                  </div>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td><code>{{ $log->ip_address ?? '127.0.0.1' }}</code></td>
              <td class="pe-3 text-muted">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, H:i:s T') }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">
                <i class="fas fa-fingerprint fa-3x mb-3 text-gray-300 d-block"></i>
                No audit trail records match the current filter criteria.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if($trails->hasPages())
    <div class="card-footer bg-white py-3">
      {{ $trails->links() }}
    </div>
  @endif
</div>
@endsection
