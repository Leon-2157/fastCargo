@extends('layouts.dashboard')

@section('title', 'Invoices & Payments')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Invoices &amp; Payments</h3>
    <p class="text-muted small mb-0">Consolidated freight billing, QRIS payment settlement and tax compliance records.</p>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<!-- Billing KPI Summary -->
<div class="row mb-4">
  <div class="col-md-4 mb-2">
    <div class="card shadow-sm border-start-warning">
      <div class="card-body">
        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Outstanding Unpaid Invoices</div>
        <div class="h4 mb-0 fw-bold text-gray-800">IDR {{ number_format($stats['total_unpaid'], 0, ',', '.') }}</div>
        <small class="text-muted">{{ $stats['count_unpaid'] }} pending invoice(s)</small>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card shadow-sm border-start-success">
      <div class="card-body">
        <div class="text-xs fw-bold text-success text-uppercase mb-1">Total Settled Freight</div>
        <div class="h4 mb-0 fw-bold text-gray-800">IDR {{ number_format($stats['total_paid'], 0, ',', '.') }}</div>
        <small class="text-muted">Paid via Dana QRIS / Wire Transfer</small>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card shadow-sm border-start-info">
      <div class="card-body">
        <div class="text-xs fw-bold text-info text-uppercase mb-1">Tax &amp; Port Dues Coverage</div>
        <div class="h4 mb-0 fw-bold text-gray-800">100% Compliant</div>
        <small class="text-muted">ASEAN single-window customs aligned</small>
      </div>
    </div>
  </div>
</div>

<!-- Invoices Table Card -->
<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
    <h6 class="m-0 font-weight-bold text-primary">Consolidated Invoices Ledger</h6>
    <div class="d-flex gap-2">
      <a href="{{ route('customer.invoices.index') }}" class="btn btn-sm {{ !request('status') || request('status') === 'ALL' ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
      <a href="{{ route('customer.invoices.index', ['status' => 'UNPAID']) }}" class="btn btn-sm {{ request('status') === 'UNPAID' ? 'btn-warning' : 'btn-outline-warning' }}">Unpaid</a>
      <a href="{{ route('customer.invoices.index', ['status' => 'PAID_SETTLED']) }}" class="btn btn-sm {{ request('status') === 'PAID_SETTLED' ? 'btn-success' : 'btn-outline-success' }}">Settled</a>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Invoice Number</th>
            <th>Related Booking</th>
            <th>Due Date</th>
            <th>Total Amount</th>
            <th>Status</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($invoices as $inv)
            <tr>
              <td class="ps-3">
                <a href="{{ route('customer.invoices.show', $inv->id) }}" class="fw-bold text-primary text-decoration-none">
                  {{ $inv->invoice_number }}
                </a>
                <div class="small text-muted">{{ $inv->created_at->format('d M Y') }}</div>
              </td>
              <td>
                @if($inv->booking)
                  <a href="{{ route('customer.bookings.show', $inv->booking->id) }}" class="text-decoration-none">
                    {{ $inv->booking->tracking_number }}
                  </a>
                  <div class="small text-muted">
                    {{ $inv->booking->route?->origin_port_id }} &rarr; {{ $inv->booking->route?->destination_port_id }}
                  </div>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td>
                <span class="{{ \Carbon\Carbon::parse($inv->due_date)->isPast() && $inv->payment_status === 'UNPAID' ? 'text-danger fw-bold' : '' }}">
                  {{ \Carbon\Carbon::parse($inv->due_date)->format('d M Y') }}
                </span>
              </td>
              <td>
                <span class="fw-bold">IDR {{ number_format($inv->total_invoice_amount, 0, ',', '.') }}</span>
              </td>
              <td>
                @if($inv->payment_status === 'PAID_SETTLED')
                  <span class="badge bg-success px-2 py-1"><i class="fas fa-check-circle me-1"></i> Paid</span>
                @elseif($inv->payment_status === 'UNPAID')
                  <span class="badge bg-warning text-dark px-2 py-1"><i class="fas fa-clock me-1"></i> Unpaid</span>
                @else
                  <span class="badge bg-secondary px-2 py-1">{{ $inv->payment_status }}</span>
                @endif
              </td>
              <td class="text-end pe-3">
                <div class="btn-group btn-group-sm">
                  <a href="{{ route('customer.invoices.show', $inv->id) }}" class="btn btn-outline-primary">
                    <i class="fas fa-eye me-1"></i> View
                  </a>
                  <a href="{{ route('customer.invoices.pdf', $inv->id) }}" class="btn btn-outline-secondary">
                    <i class="fas fa-file-pdf"></i>
                  </a>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center py-5 text-muted">
                <i class="fas fa-receipt fa-3x mb-3 text-gray-300 d-block"></i>
                No invoices found in your record.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if($invoices->hasPages())
    <div class="card-footer bg-white py-3">
      {{ $invoices->links() }}
    </div>
  @endif
</div>
@endsection

