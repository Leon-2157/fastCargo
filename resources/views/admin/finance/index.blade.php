@extends('layouts.dashboard')

@section('title', 'Financial Cockpit & Revenue Splits')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Financial Cockpit &amp; Settlement Pools</h3>
    <p class="text-muted small mb-0">Platform take rates, operator disbursements, and Dana Business QRIS settlement volume.</p>
  </div>
  <div>
    <a href="{{ route('admin.finance.escrow') }}" class="btn btn-primary btn-sm shadow-sm">
      <i class="fas fa-lock me-1"></i> Inspect Escrow Ledger
    </a>
  </div>
</div>

<!-- Revenue Split Metric Cards -->
<div class="row mb-4">
  <div class="col-md-3 col-6 mb-2">
    <div class="card shadow-sm border-start-primary">
      <div class="card-body">
        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Gross Freight Invoiced</div>
        <div class="h5 mb-0 fw-bold text-gray-800">IDR {{ number_format($totalPaid + $totalUnpaid, 0, ',', '.') }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-6 mb-2">
    <div class="card shadow-sm border-start-success">
      <div class="card-body">
        <div class="text-xs fw-bold text-success text-uppercase mb-1">Settled Net Profit (12%)</div>
        <div class="h5 mb-0 fw-bold text-success">IDR {{ number_format($platformCut, 0, ',', '.') }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-6 mb-2">
    <div class="card shadow-sm border-start-warning">
      <div class="card-body">
        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Operator Haulage Pool (73%)</div>
        <div class="h5 mb-0 fw-bold text-warning">IDR {{ number_format($operatorPool, 0, ',', '.') }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-6 mb-2">
    <div class="card shadow-sm border-start-info">
      <div class="card-body">
        <div class="text-xs fw-bold text-info text-uppercase mb-1">Terminal &amp; Port Dues (15%)</div>
        <div class="h5 mb-0 fw-bold text-info">IDR {{ number_format($portDues, 0, ',', '.') }}</div>
      </div>
    </div>
  </div>
</div>

<!-- Recent Transactions Card -->
<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white">
    <h6 class="m-0 font-weight-bold text-primary">Recent Gateway Transactions</h6>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Transaction Ref</th>
            <th>Gateway</th>
            <th>Channel</th>
            <th>Settled Amount</th>
            <th>Status</th>
            <th>Paid Timestamp</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recentTransactions as $tx)
            <tr>
              <td class="ps-3 fw-bold text-primary"><code>{{ $tx->transaction_ref }}</code></td>
              <td><span class="badge bg-light text-dark border">{{ $tx->payment_gateway }}</span></td>
              <td><span class="badge bg-info">{{ $tx->payment_channel }}</span></td>
              <td class="fw-bold">IDR {{ number_format($tx->amount, 0, ',', '.') }}</td>
              <td>
                <span class="badge bg-success">{{ $tx->payment_status }}</span>
              </td>
              <td class="small text-muted">{{ \Carbon\Carbon::parse($tx->paid_at)->format('d M Y, H:i T') }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center py-5 text-muted">
                No recent payment transactions recorded.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

