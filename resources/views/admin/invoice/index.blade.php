@extends('layouts.dashboard')

@section('title', 'Master Invoices Ledger')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Master Invoices Governance Ledger</h3>
    <p class="text-muted small mb-0">Platform-wide billing records, payment reconciliation, and Dana QRIS settlement statuses.</p>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
    <h6 class="m-0 font-weight-bold text-primary">Consolidated Invoices Ledger</h6>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.invoices.index') }}" class="btn btn-sm {{ !request('status') || request('status') === 'ALL' ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
      <a href="{{ route('admin.invoices.index', ['status' => 'UNPAID']) }}" class="btn btn-sm {{ request('status') === 'UNPAID' ? 'btn-warning text-dark' : 'btn-outline-warning' }}">Unpaid</a>
      <a href="{{ route('admin.invoices.index', ['status' => 'PAID_SETTLED']) }}" class="btn btn-sm {{ request('status') === 'PAID_SETTLED' ? 'btn-success' : 'btn-outline-success' }}">Settled</a>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Invoice Number</th>
            <th>Shipper / Legal Entity</th>
            <th>Booking Resi</th>
            <th>Total Amount</th>
            <th>Due Date</th>
            <th>Status</th>
            <th class="text-end pe-3">Reconciliation</th>
          </tr>
        </thead>
        <tbody>
          @forelse($invoices as $inv)
            <tr>
              <td class="ps-3 fw-bold text-primary">{{ $inv->invoice_number }}</td>
              <td>
                <div class="fw-semibold text-dark">{{ $inv->company?->company_name ?? 'Individual' }}</div>
                <small class="text-muted">NPWP: {{ $inv->company?->npwp ?? 'N/A' }}</small>
              </td>
              <td>
                @if($inv->booking)
                  <span class="badge bg-light text-dark border">{{ $inv->booking->tracking_number }}</span>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="fw-bold">IDR {{ number_format($inv->total_invoice_amount, 0, ',', '.') }}</td>
              <td>{{ \Carbon\Carbon::parse($inv->due_date)->format('d M Y') }}</td>
              <td>
                @if($inv->payment_status === 'PAID_SETTLED')
                  <span class="badge bg-success">PAID SETTLED</span>
                @else
                  <span class="badge bg-warning text-dark">UNPAID</span>
                @endif
              </td>
              <td class="text-end pe-3">
                @if($inv->payment_status !== 'PAID_SETTLED')
                  <form method="POST" action="{{ route('admin.invoices.markPaid', $inv->id) }}" class="d-inline" onsubmit="return confirm('Override & mark this invoice as PAID (wire settlement)?');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-success">
                      <i class="fas fa-check me-1"></i> Mark Paid
                    </button>
                  </form>
                @else
                  <span class="text-success small fw-semibold"><i class="fas fa-check-circle"></i> Settled</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">
                No invoices found.
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

