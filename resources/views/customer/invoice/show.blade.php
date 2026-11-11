@extends('layouts.dashboard')

@section('title', 'Invoice ' . $invoice->invoice_number)

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('customer.invoices.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
      <i class="fas fa-arrow-left me-1"></i> Back to Invoices
    </a>
    <h3 class="text-dark mb-0 fw-bold">Invoice {{ $invoice->invoice_number }}</h3>
    <p class="text-muted small mb-0">Issued on {{ $invoice->created_at->format('d F Y') }} &bull; Due Date: {{ \Carbon\Carbon::parse($invoice->due_date)->format('d F Y') }}</p>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('customer.invoices.pdf', $invoice->id) }}" class="btn btn-outline-primary btn-sm">
      <i class="fas fa-file-pdf me-1"></i> Download PDF
    </a>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="row">
  <!-- Left Column: Invoice Document Card -->
  <div class="col-lg-8">
    <div class="card shadow mb-4">
      <div class="card-body p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center border-bottom pb-4 mb-4">
          <div>
            <h4 class="fw-bold text-primary mb-1"><i class="fas fa-shipping-fast me-2"></i>FastCargo Enterprise</h4>
            <p class="text-muted small mb-0">ASEAN Cross-Border Multi-Modal Logistics Operating System</p>
          </div>
          <div class="text-end">
            <span class="badge {{ $invoice->payment_status === 'PAID_SETTLED' ? 'bg-success' : 'bg-warning text-dark' }} px-3 py-2 fs-6">
              {{ str_replace('_', ' ', $invoice->payment_status) }}
            </span>
          </div>
        </div>

        <!-- Billed To & Corridor Details -->
        <div class="row mb-4">
          <div class="col-md-6">
            <h6 class="fw-bold text-muted text-uppercase small">Billed To (Consignee / Shipper)</h6>
            <h5 class="fw-bold text-dark mb-1">{{ $invoice->company->company_name ?? auth('customer')->user()->name }}</h5>
            <p class="text-muted small mb-0">
              {{ $invoice->company->company_address ?? 'Kawasan Industri Nusantara, Jakarta, Indonesia' }}<br>
              NPWP: {{ $invoice->company->npwp ?? '01.234.567.8-012.000' }} &bull; NIB: {{ $invoice->company->nib ?? '1234567890123' }}
            </p>
          </div>
          <div class="col-md-6 text-md-end">
            <h6 class="fw-bold text-muted text-uppercase small">Consignment Tracking</h6>
            @if($invoice->booking)
              <h5 class="fw-bold text-primary mb-1">
                <a href="{{ route('customer.bookings.show', $invoice->booking->id) }}" class="text-decoration-none">
                  {{ $invoice->booking->tracking_number }}
                </a>
              </h5>
              <p class="text-muted small mb-0">
                Route: {{ $invoice->booking->route?->origin_port_id }} &rarr; {{ $invoice->booking->route?->destination_port_id }}<br>
                Mode: {{ $invoice->booking->route?->transport_mode ?? 'SEA' }} Corridor
              </p>
            @else
              <p class="text-muted small">Standard Freight Fee</p>
            @endif
          </div>
        </div>

        <!-- Itemized Breakdown -->
        <div class="table-responsive mb-4">
          <table class="table table-bordered">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Line Item Description</th>
                <th class="text-center">Rate Type</th>
                <th class="text-end">Qty / Weight</th>
                <th class="text-end pe-3">Subtotal (IDR)</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="ps-3">
                  <strong>Corridor Base Freight Fee</strong>
                  <div class="small text-muted">Ocean/Air haulage &amp; transit slot allocation</div>
                </td>
                <td class="text-center"><span class="badge bg-light text-dark border">Base</span></td>
                <td class="text-end">1 Consignment</td>
                <td class="text-end pe-3">{{ number_format($invoice->total_invoice_amount * 0.75, 0, ',', '.') }}</td>
              </tr>
              <tr>
                <td class="ps-3">
                  <strong>Terminal Handling Charges (THC) &amp; Port Dues</strong>
                  <div class="small text-muted">Gate-in inspection, quay crane moves, terminal handling</div>
                </td>
                <td class="text-center"><span class="badge bg-light text-dark border">Fixed</span></td>
                <td class="text-end">1 Unit</td>
                <td class="text-end pe-3">{{ number_format($invoice->total_invoice_amount * 0.15, 0, ',', '.') }}</td>
              </tr>
              <tr>
                <td class="ps-3">
                  <strong>Customs Pre-Clearance &amp; Marine Cargo Indemnity</strong>
                  <div class="small text-muted">Automated ASEAN single window customs filing &amp; 0.2% marine risk coverage</div>
                </td>
                <td class="text-center"><span class="badge bg-light text-dark border">Indemnity</span></td>
                <td class="text-end">1 Policy</td>
                <td class="text-end pe-3">{{ number_format($invoice->total_invoice_amount * 0.10, 0, ',', '.') }}</td>
              </tr>
            </tbody>
            <tfoot class="table-light">
              <tr>
                <th colspan="3" class="text-end">Grand Total Payable:</th>
                <th class="text-end pe-3 text-primary h5 fw-bold">IDR {{ number_format($invoice->total_invoice_amount, 0, ',', '.') }}</th>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="alert alert-secondary small mb-0">
          <i class="fas fa-info-circle me-1"></i> Payment terms: Immediate settlement via Dana Business QRIS or direct wire transfer. All payments are secured under FastCargo Escrow until electronic Proof of Delivery (ePOD) confirmation.
        </div>
      </div>
    </div>
  </div>

  <!-- Right Column: Dana QRIS Payment Simulator -->
  <div class="col-lg-4">
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-qrcode me-1"></i> Dana Business QRIS</h6>
        <span class="badge bg-info">QRIS Standard</span>
      </div>
      <div class="card-body text-center p-4">
        @if($invoice->payment_status === 'PAID_SETTLED')
          <div class="text-success mb-3">
            <i class="fas fa-check-circle fa-5x"></i>
          </div>
          <h5 class="fw-bold text-success mb-1">Payment Settled</h5>
          <p class="text-muted small mb-3">Settled via Dana Business QRIS on {{ \Carbon\Carbon::parse($invoice->paid_at)->format('d M Y, H:i T') }}</p>

          @if($invoice->paymentTransactions->isNotEmpty())
            @php $tx = $invoice->paymentTransactions->first(); @endphp
            <div class="bg-light p-3 rounded text-start small mb-3 border">
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Transaction Ref:</span>
                <span class="fw-bold">{{ $tx->transaction_ref }}</span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Gateway:</span>
                <span class="fw-bold">Dana Business API</span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-muted">Amount Paid:</span>
                <span class="fw-bold text-success">IDR {{ number_format($tx->amount, 0, ',', '.') }}</span>
              </div>
            </div>
          @endif

          <a href="{{ route('customer.invoices.pdf', $invoice->id) }}" class="btn btn-outline-primary btn-sm w-100">
            <i class="fas fa-download me-1"></i> Download Tax Receipt
          </a>
        @else
          <!-- QR Code Display -->
          <div class="p-3 border rounded bg-light mb-3 d-inline-block">
            <div style="background: #fff; padding: 15px; border-radius: 8px;">
              <i class="fas fa-qrcode fa-7x text-dark"></i>
            </div>
          </div>

          <div class="h5 fw-bold text-primary mb-1">IDR {{ number_format($invoice->total_invoice_amount, 0, ',', '.') }}</div>
          <div class="small text-muted mb-4">NMID: ID10202611090123 &bull; FastCargo Indonesia</div>

          <form method="POST" action="{{ route('customer.invoices.pay', $invoice->id) }}">
            @csrf
            <button type="submit" class="btn btn-success btn-lg w-100 shadow mb-2">
              <i class="fas fa-wallet me-1"></i> Simulate Dana QRIS Pay
            </button>
          </form>
          <div class="text-muted text-xs">Simulates real-time webhook callback from Dana Business gateway</div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

