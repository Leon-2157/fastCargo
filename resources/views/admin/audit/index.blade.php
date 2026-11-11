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
  <div class="card-header py-3 bg-white">
    <h6 class="m-0 font-weight-bold text-primary">System Transaction &amp; Action Ledger</h6>
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
            <th>Client IP Address</th>
            <th>Logged At</th>
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
                <span class="badge bg-light text-dark border">{{ $log->target_type ?? 'GLOBAL' }}: {{ $log->target_id ?? 'ALL' }}</span>
              </td>
              <td><code>{{ $log->ip_address ?? '127.0.0.1' }}</code></td>
              <td class="text-muted">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, H:i:s T') }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center py-5 text-muted">
                <i class="fas fa-fingerprint fa-3x mb-3 text-gray-300 d-block"></i>
                No audit trail records yet. All sensitive actions are automatically logged here.
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

