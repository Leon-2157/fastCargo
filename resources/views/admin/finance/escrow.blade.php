@extends('layouts.dashboard')

@section('title', 'FastCargo Escrow Ledger')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('admin.finance.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
      <i class="fas fa-arrow-left me-1"></i> Back to Finance Cockpit
    </a>
    <h3 class="text-dark mb-0 fw-bold">Multi-Milestone Escrow Ledger</h3>
    <p class="text-muted small mb-0">Transparent escrow governance: 30% Gate-In &bull; 40% Vessel Sailing &bull; 30% ePOD Delivery Confirmation.</p>
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
    <h6 class="m-0 font-weight-bold text-primary">Consignment Escrow Vault Entries</h6>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Booking Resi</th>
            <th>Total Escrow Held</th>
            <th>Milestone 1 (30% Gate-In)</th>
            <th>Milestone 2 (40% Sailing)</th>
            <th>Milestone 3 (30% Delivery)</th>
            <th>Escrow Status</th>
            <th class="text-end pe-3">Manual Release</th>
          </tr>
        </thead>
        <tbody>
          @forelse($escrows as $escrow)
            <tr>
              <td class="ps-3 fw-bold text-primary">
                {{ $escrow->cargoBooking?->tracking_number ?? 'Booking' }}
              </td>
              <td class="fw-bold">IDR {{ number_format($escrow->total_paid_amount, 0, ',', '.') }}</td>
              <td>
                <div class="small fw-semibold">IDR {{ number_format($escrow->milestone_1_pickup_amount, 0, ',', '.') }}</div>
                @if($escrow->milestone_1_released_at)
                  <span class="badge bg-success">Released</span>
                @else
                  <span class="badge bg-warning text-dark">Locked</span>
                @endif
              </td>
              <td>
                <div class="small fw-semibold">IDR {{ number_format($escrow->milestone_2_sailing_amount, 0, ',', '.') }}</div>
                @if($escrow->milestone_2_released_at)
                  <span class="badge bg-success">Released</span>
                @else
                  <span class="badge bg-warning text-dark">Locked</span>
                @endif
              </td>
              <td>
                <div class="small fw-semibold">IDR {{ number_format($escrow->milestone_3_delivered_amount, 0, ',', '.') }}</div>
                @if($escrow->milestone_3_released_at)
                  <span class="badge bg-success">Released</span>
                @else
                  <span class="badge bg-warning text-dark">Locked</span>
                @endif
              </td>
              <td>
                <span class="badge bg-primary">{{ str_replace('_', ' ', $escrow->escrow_status) }}</span>
              </td>
              <td class="text-end pe-3">
                @if($escrow->escrow_status !== 'FULLY_SETTLED')
                  <form method="POST" action="{{ route('admin.finance.escrow.release', $escrow->id) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="milestone" value="{{ $escrow->milestone_1_released_at ? ($escrow->milestone_2_released_at ? 3 : 2) : 1 }}">
                    <button type="submit" class="btn btn-sm btn-outline-success">
                      Release Next
                    </button>
                  </form>
                @else
                  <span class="text-muted small">Settled</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">
                No active escrow entries found.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if($escrows->hasPages())
    <div class="card-footer bg-white py-3">
      {{ $escrows->links() }}
    </div>
  @endif
</div>
@endsection

