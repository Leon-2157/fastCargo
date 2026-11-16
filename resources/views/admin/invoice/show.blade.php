@extends('layouts.dashboard')

@section('title', 'Invoice Dossier — ' . $invoice->invoice_number)

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
      <i class="fas fa-arrow-left me-1"></i> Back to Master Invoices Ledger
    </a>
    <h3 class="text-dark mb-0 fw-bold">Invoice Dossier: {{ $invoice->invoice_number }}</h3>
    <p class="text-muted small mb-0">Billing inspection, escrow funding verification, and gateway settlement audit trail.</p>
  </div>
  <div class="d-flex align-items-center gap-2">
    @if($invoice->payment_status === 'PAID_SETTLED')
      <span class="badge bg-success px-3 py-2 fs-6"><i class="fas fa-check-circle me-1"></i> PAID &amp; SETTLED</span>
    @else
      <span class="badge bg-warning text-dark px-3 py-2 fs-6"><i class="fas fa-clock me-1"></i> UNPAID</span>
      <form method="POST" action="{{ route('admin.invoices.markPaid', $invoice->id) }}" class="d-inline" onsubmit="return confirm('Authorize manual bank wire settlement and fund escrow for this invoice?');">
        @csrf
        <button type="submit" class="btn btn-success btn-sm shadow-sm fw-bold">
          <i class="fas fa-check-double me-1"></i> Authorize Manual Settlement
        </button>
      </form>
    @endif
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if(session('info'))
  <div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="fas fa-info-circle me-1"></i> {{ session('info') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="row g-4">
  <!-- Left Column: Billing Summary & Shipper Entity -->
  <div class="col-lg-5">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-file-invoice-dollar me-2"></i>Financial Summary</h6>
      </div>
      <div class="card-body">
        <div class="p-3 bg-light rounded border mb-3 text-center">
          <div class="text-xs text-muted text-uppercase fw-bold">Total Invoice Amount</div>
          <div class="h3 fw-bold text-dark mb-0">{{ $invoice->currency ?? 'IDR' }} {{ number_format($invoice->total_invoice_amount, 0, ',', '.') }}</div>
        </div>
        <dl class="row mb-0 small">
          <dt class="col-sm-5 text-muted">Invoice Number</dt>
          <dd class="col-sm-7 fw-bold text-primary"><code>{{ $invoice->invoice_number }}</code></dd>

          <dt class="col-sm-5 text-muted">Payment Status</dt>
          <dd class="col-sm-7">
            @if($invoice->payment_status === 'PAID_SETTLED')
              <span class="badge bg-success">PAID_SETTLED</span>
            @else
              <span class="badge bg-warning text-dark">UNPAID</span>
            @endif
          </dd>

          <dt class="col-sm-5 text-muted">Issue Date</dt>
          <dd class="col-sm-7">{{ $invoice->created_at?->format('d M Y, H:i T') ?? '—' }}</dd>

          <dt class="col-sm-5 text-muted">Due Date</dt>
          <dd class="col-sm-7 fw-semibold">{{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') : '—' }}</dd>

          <dt class="col-sm-5 text-muted">Settled Timestamp</dt>
          <dd class="col-sm-7">{{ $invoice->paid_at ? \Carbon\Carbon::parse($invoice->paid_at)->format('d M Y, H:i T') : 'Pending Settlement' }}</dd>
        </dl>
      </div>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-building me-2"></i>Billed Corporate / Shipper Entity</h6>
      </div>
      <div class="card-body">
        @if($invoice->company)
          <dl class="row mb-0 small">
            <dt class="col-sm-4 text-muted">Legal Entity</dt>
            <dd class="col-sm-8 fw-bold text-dark">{{ $invoice->company->company_name }}</dd>

            <dt class="col-sm-4 text-muted">NPWP / Tax ID</dt>
            <dd class="col-sm-8"><code>{{ $invoice->company->npwp ?? 'N/A' }}</code></dd>

            <dt class="col-sm-4 text-muted">NIB License</dt>
            <dd class="col-sm-8"><code>{{ $invoice->company->nib ?? 'N/A' }}</code></dd>

            <dt class="col-sm-4 text-muted">Account Owner</dt>
            <dd class="col-sm-8">
              @if($invoice->company->user)
                <a href="{{ route('admin.users.show', $invoice->company->user->id) }}" class="text-decoration-none fw-semibold">
                  {{ $invoice->company->user->name }}
                </a>
                <div class="text-muted">{{ $invoice->company->user->email }}</div>
              @else
                <span class="text-muted">—</span>
              @endif
            </dd>

            <dt class="col-sm-4 text-muted">Billing Address</dt>
            <dd class="col-sm-8 mb-0">{{ $invoice->company->billing_address ?? '—' }}</dd>
          </dl>
        @else
          <p class="text-muted small mb-0">Billed to retail / individual shipper account.</p>
        @endif
      </div>
    </div>
  </div>

  <!-- Right Column: Linked Consignment, Escrow Vault & Payment Transactions -->
  <div class="col-lg-7">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-ship me-2"></i>Linked Consignment Waybill &amp; Cargo Items</h6>
        @if($invoice->booking)
          <span class="badge bg-light text-dark border">{{ $invoice->booking->tracking_number }}</span>
        @endif
      </div>
      <div class="card-body">
        @if($invoice->booking)
          <div class="row g-3 mb-3 small">
            <div class="col-sm-6">
              <div class="text-muted">Maritime Corridor</div>
              <div class="fw-bold text-dark">
                {{ $invoice->booking->route?->originPort?->port_name ?? 'Origin Port' }}
                <i class="fas fa-arrow-right mx-1 text-primary"></i>
                {{ $invoice->booking->route?->destinationPort?->port_name ?? 'Destination Port' }}
              </div>
            </div>
            <div class="col-sm-3">
              <div class="text-muted">Cargo Status</div>
              <span class="badge bg-info">{{ $invoice->booking->cargo_status }}</span>
            </div>
            <div class="col-sm-3">
              <div class="text-muted">Total Weight / Vol</div>
              <div class="fw-semibold">{{ number_format((float) $invoice->booking->total_weight_kg, 1) }} kg / {{ number_format((float) $invoice->booking->total_volume_cbm, 2) }} CBM</div>
            </div>
          </div>

          @if($invoice->booking->items && $invoice->booking->items->isNotEmpty())
            <div class="table-responsive">
              <table class="table table-sm table-bordered align-middle mb-0 small">
                <thead class="table-light">
                  <tr>
                    <th>Commodity Description</th>
                    <th>HS Code</th>
                    <th>Qty</th>
                    <th>Weight (kg)</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($invoice->booking->items as $item)
                    <tr>
                      <td class="fw-semibold">{{ $item->commodity_description ?? $item->item_name ?? 'Cargo Item' }}</td>
                      <td><code>{{ $item->hs_code ?? '—' }}</code></td>
                      <td>{{ $item->quantity ?? 1 }} {{ $item->packaging_type ?? 'Pkg' }}</td>
                      <td>{{ number_format((float) ($item->weight_kg ?? 0), 1) }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        @else
          <p class="text-muted small mb-0">No consignment booking linked to this invoice.</p>
        @endif
      </div>
    </div>

    <!-- Escrow Ledger Status -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-lock me-2"></i>Escrow Vault &amp; Milestone Allocation</h6>
        <a href="{{ route('admin.finance.escrow') }}" class="btn btn-sm btn-outline-primary">Open Escrow Ledger</a>
      </div>
      <div class="card-body">
        @php $escrow = $invoice->booking?->escrowLedger; @endphp
        @if($escrow)
          <div class="row text-center g-2">
            <div class="col-md-4">
              <div class="p-2 border rounded bg-light">
                <div class="text-xs text-muted fw-bold">M1: Gate-In (30%)</div>
                <div class="fw-bold text-dark">IDR {{ number_format($escrow->milestone_1_pickup_amount, 0, ',', '.') }}</div>
                <span class="badge {{ $escrow->milestone_1_released_at ? 'bg-success' : 'bg-warning text-dark' }}">
                  {{ $escrow->milestone_1_released_at ? 'Released' : 'Locked' }}
                </span>
              </div>
            </div>
            <div class="col-md-4">
              <div class="p-2 border rounded bg-light">
                <div class="text-xs text-muted fw-bold">M2: Vessel Sailing (40%)</div>
                <div class="fw-bold text-dark">IDR {{ number_format($escrow->milestone_2_sailing_amount, 0, ',', '.') }}</div>
                <span class="badge {{ $escrow->milestone_2_released_at ? 'bg-success' : 'bg-warning text-dark' }}">
                  {{ $escrow->milestone_2_released_at ? 'Released' : 'Locked' }}
                </span>
              </div>
            </div>
            <div class="col-md-4">
              <div class="p-2 border rounded bg-light">
                <div class="text-xs text-muted fw-bold">M3: ePOD Delivery (30%)</div>
                <div class="fw-bold text-dark">IDR {{ number_format($escrow->milestone_3_delivered_amount, 0, ',', '.') }}</div>
                <span class="badge {{ $escrow->milestone_3_released_at ? 'bg-success' : 'bg-warning text-dark' }}">
                  {{ $escrow->milestone_3_released_at ? 'Released' : 'Locked' }}
                </span>
              </div>
            </div>
          </div>
        @else
          <p class="text-muted small mb-0">Escrow vault will be automatically funded once this invoice is settled.</p>
        @endif
      </div>
    </div>

    <!-- Gateway Transactions -->
    <div class="card shadow-sm">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-receipt me-2"></i>Gateway Payment Attempts &amp; Settlement Logs</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Transaction Ref</th>
                <th>Gateway / Channel</th>
                <th>Amount</th>
                <th>Status</th>
                <th class="pe-3">Paid At</th>
              </tr>
            </thead>
            <tbody>
              @forelse($invoice->paymentTransactions as $tx)
                <tr>
                  <td class="ps-3 fw-bold"><code>{{ $tx->transaction_ref }}</code></td>
                  <td>
                    <span class="badge bg-light text-dark border">{{ $tx->payment_gateway }}</span>
                    <span class="badge bg-info">{{ $tx->payment_channel }}</span>
                  </td>
                  <td class="fw-bold">IDR {{ number_format($tx->amount, 0, ',', '.') }}</td>
                  <td>
                    @if(in_array($tx->payment_status, ['SETTLEMENT', 'SETTLED', 'PAID_SETTLED'], true))
                      <span class="badge bg-success">{{ $tx->payment_status }}</span>
                    @else
                      <span class="badge bg-warning text-dark">{{ $tx->payment_status }}</span>
                    @endif
                  </td>
                  <td class="pe-3 text-muted">{{ $tx->paid_at ? \Carbon\Carbon::parse($tx->paid_at)->format('d M Y, H:i T') : '—' }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="text-center py-4 text-muted">No gateway payment transactions recorded for this invoice yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

