@extends('layouts.dashboard')

@section('title', 'Invoice ' . $invoice->invoice_number)

@push('styles')
<style>
  .qris-checkout-svg svg {
    width: 100%;
    max-width: 240px;
    height: auto;
    display: block;
  }
</style>
@endpush

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

      </div>
    </div>
  </div>

  <!-- Right Column: SGpay Direct QRIS Payment Gateway -->
  <div class="col-lg-4">
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-qrcode me-1"></i> PayG / SGpay Direct QRIS</h6>
        @if(isset($qrisData) && $qrisData['is_mock'])
          <span class="badge bg-warning text-dark"><i class="fas fa-flask me-1"></i> Smart Sandbox</span>
        @else
          <span class="badge bg-success"><i class="fas fa-bolt me-1"></i> Direct QRIS Live</span>
        @endif
      </div>
      <div class="card-body text-center p-4">
        @if($invoice->payment_status === 'PAID_SETTLED')
          <div class="text-success mb-3">
            <i class="fas fa-check-circle fa-5x"></i>
          </div>
          <h5 class="fw-bold text-success mb-1">Payment Settled</h5>
          <p class="text-muted small mb-3">Settled via SGpay Direct QRIS on {{ \Carbon\Carbon::parse($invoice->paid_at ?? now())->format('d M Y, H:i T') }}</p>

          <div class="alert alert-success text-start small mb-3 py-2 px-3 border-0 bg-success bg-opacity-10 text-success">
            <i class="fas fa-shield-alt me-1"></i> <strong>Escrow Active:</strong> Funds are locked in FastCargo Escrow Ledger and released progressively to the operator across milestones.
          </div>

          @if($invoice->paymentTransactions->isNotEmpty())
            @php $tx = $invoice->paymentTransactions->first(); @endphp
            <div class="bg-light p-3 rounded text-start small mb-3 border">
              <div class="d-flex justify-content-between mb-1 gap-2">
                <span class="text-muted">Transaction Ref:</span>
                <span class="fw-bold font-monospace text-break text-end">{{ $tx->transaction_ref }}</span>
              </div>
              <div class="d-flex justify-content-between mb-1 gap-2">
                <span class="text-muted">Gateway:</span>
                <span class="fw-bold text-end">{{ $tx->payment_gateway ?? 'SGPAY' }} ({{ $tx->payment_channel ?? 'QRIS' }})</span>
              </div>
              <div class="d-flex justify-content-between gap-2">
                <span class="text-muted">Amount Paid:</span>
                <span class="fw-bold text-success">IDR {{ number_format($tx->amount, 0, ',', '.') }}</span>
              </div>
            </div>
          @endif

          <a href="{{ route('customer.invoices.pdf', $invoice->id) }}" class="btn btn-outline-primary btn-sm w-100">
            <i class="fas fa-download me-1"></i> Download Tax Receipt
          </a>
        @else
          @php
            $baseAmount = (float) ($qrisData['amount'] ?? $invoice->total_invoice_amount);
            $uniqueCode = (int) ($qrisData['unique_code'] ?? 0);
            $payableAmount = (float) ($qrisData['payable_amount'] ?? $invoice->total_invoice_amount);
            $payableInteger = (int) round($payableAmount);
            $expiresTs = isset($qrisData['expires_at']) ? $qrisData['expires_at']->timestamp : (now()->timestamp + 900);
            $qrPayloadText = $qrisData['qris_payload'] ?? $qrisData['qr_string'] ?? '';
          @endphp

          <!-- QR Code Display SVG -->
          <div class="p-3 border rounded bg-light mb-3 d-inline-block shadow-sm position-relative w-100">
            <div class="bg-white p-2 rounded d-flex justify-content-center align-items-center" style="min-height: 240px;">
              @if(! empty($qrisData['qr_svg']))
                <div class="qris-checkout-svg w-100 d-flex justify-content-center">
                  {!! $qrisData['qr_svg'] !!}
                </div>
              @else
                <i class="fas fa-qrcode fa-7x text-dark"></i>
              @endif
            </div>
            <div class="mt-2 d-flex justify-content-between align-items-center text-muted small px-1">
              <span><i class="fas fa-clock text-danger me-1"></i> Expires in <strong id="qrisCountdown">--:--</strong></span>
              <span class="badge bg-secondary">{{ $qrisData['acquirer_label'] ?? 'ASPI / BI QRIS' }}</span>
            </div>
          </div>

          <!-- Merchant Identity & Exact Payable Breakdown -->
          <div class="bg-light border rounded p-3 mb-3 text-start small">
            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom gap-2">
              <span class="text-muted">Merchant:</span>
              <span class="fw-bold text-dark text-end">
                {{ $qrisData['merchant_name'] ?? 'FASTCARGO ASEAN LOGISTICS' }}
                @if(! empty($qrisData['merchant_city']))
                  <span class="text-muted fw-normal d-block" style="font-size: 11px;">{{ $qrisData['merchant_city'] }}</span>
                @endif
              </span>
            </div>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted">Base Invoice:</span>
              <span class="fw-semibold" style="font-variant-numeric: tabular-nums;">IDR {{ number_format($baseAmount, 0, ',', '.') }}</span>
            </div>
            @if($uniqueCode > 0)
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Unique Verification Code:</span>
                <span class="fw-semibold text-warning-emphasis" style="font-variant-numeric: tabular-nums;">+ {{ number_format($uniqueCode, 0, ',', '.') }}</span>
              </div>
            @endif
            <div class="d-flex justify-content-between align-items-center pt-2 border-top gap-2">
              <span class="fw-bold text-dark">Total Bayar Tepat:</span>
              <div class="d-flex align-items-center gap-2">
                <span class="h6 fw-bold text-primary mb-0" style="font-variant-numeric: tabular-nums;">IDR {{ number_format($payableAmount, 0, ',', '.') }}</span>
                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                  id="btn-copy-exact-amount"
                  data-copy-value="{{ $payableInteger }}"
                  aria-label="Copy exact payable amount">
                  <i class="fas fa-copy"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
            <span class="small text-secondary text-truncate">Ref: <code class="font-monospace">{{ $qrisData['transaction_ref'] ?? 'SGPAY-PENDING' }}</code></span>
            @if($qrPayloadText !== '')
              <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 flex-shrink-0"
                id="btn-copy-qris-payload"
                data-copy-value="{{ $qrPayloadText }}"
                aria-label="Copy QRIS payload string">
                <i class="fas fa-copy me-1"></i> Copy QRIS
              </button>
            @endif
          </div>

          <!-- Real-Time Polling Status Banner -->
          <div id="qris-polling-status" class="alert alert-info border py-2 px-3 small text-start mb-3 d-flex align-items-center gap-2" role="status">
            <span class="spinner-border spinner-border-sm text-info flex-shrink-0" aria-hidden="true"></span>
            <span>Menunggu konfirmasi pembayaran &hellip; <span class="text-muted">(auto-checking every 4s)</span></span>
          </div>

          <!-- Escrow Guarantee Badge -->
          <div class="border rounded p-2 mb-3 bg-light text-start small">
            <div class="d-flex align-items-center text-primary fw-semibold mb-1">
              <i class="fas fa-lock me-1"></i> 3-Stage Escrow Protection:
            </div>
            <div class="text-muted text-xs">
              <span class="badge bg-light text-dark border me-1">30% Gate-In</span>
              <span class="badge bg-light text-dark border me-1">40% Sailing</span>
              <span class="badge bg-light text-dark border">30% ePOD</span>
            </div>
          </div>

          <form method="POST" action="{{ route('customer.invoices.pay', $invoice->id, false) }}" class="mb-2">
            @csrf
            <input type="hidden" name="action" value="regenerate">
            <button type="submit" class="btn btn-outline-primary btn-sm w-100 fw-semibold">
              <i class="fas fa-sync-alt me-1"></i> Regenerate QR Code
            </button>
          </form>

          @if(! app()->isProduction())
            <form method="POST" action="{{ route('customer.invoices.pay', $invoice->id, false) }}">
              @csrf
              <button type="submit" class="btn btn-success btn-sm w-100 shadow-sm fw-bold">
                <i class="fas fa-wallet me-1"></i> Simulate Instant Pay (Test Mode)
              </button>
            </form>
            <div class="text-muted mt-1" style="font-size: 11px;">Non-production test shortcut: settles QRIS, credits Escrow Ledger &amp; dispatches WhatsApp receipt.</div>
          @endif
        @endif
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
  (function() {
    function copyValueWithFeedback(buttonEl) {
      if (!buttonEl) return;
      const val = buttonEl.getAttribute('data-copy-value');
      if (!val) return;
      const orig = buttonEl.innerHTML;
      const showCopied = function() {
        buttonEl.innerHTML = '<i class="fas fa-check text-success"></i>';
        setTimeout(function() {
          buttonEl.innerHTML = orig;
        }, 1800);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(val).then(showCopied).catch(fallbackCopy);
      } else {
        fallbackCopy();
      }
      function fallbackCopy() {
        const ta = document.createElement('textarea');
        ta.value = val;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
          if (document.execCommand('copy')) showCopied();
        } catch (e) {}
        document.body.removeChild(ta);
      }
    }

    const btnCopyAmount = document.getElementById('btn-copy-exact-amount');
    if (btnCopyAmount) {
      btnCopyAmount.addEventListener('click', function() { copyValueWithFeedback(btnCopyAmount); });
    }
    const btnCopyQris = document.getElementById('btn-copy-qris-payload');
    if (btnCopyQris) {
      btnCopyQris.addEventListener('click', function() { copyValueWithFeedback(btnCopyQris); });
    }

    // Dynamic countdown derived from backend expires_at Unix timestamp
    const el = document.getElementById('qrisCountdown');
    if (el) {
      const expiresTs = {{ isset($expiresTs) ? (int) $expiresTs : (now()->timestamp + 900) }};
      function renderCountdown() {
        const remaining = Math.max(0, expiresTs - Math.floor(Date.now() / 1000));
        if (remaining <= 0) {
          el.innerText = 'EXPIRED';
          el.classList.add('text-danger');
          return false;
        }
        const m = Math.floor(remaining / 60);
        const s = remaining % 60;
        el.innerText = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        return true;
      }
      if (renderCountdown()) {
        const cdTimer = setInterval(function() {
          if (!renderCountdown()) clearInterval(cdTimer);
        }, 1000);
        window.addEventListener('beforeunload', function() { clearInterval(cdTimer); });
      }
    }

    // Real-time 4-second invoice status polling while UNPAID
    @if($invoice->payment_status !== 'PAID_SETTLED')
    const statusBanner = document.getElementById('qris-polling-status');
    const statusEndpoint = @json(route('customer.invoices.status', $invoice->id, false));
    let pollInterval = setInterval(async function() {
      try {
        const resp = await fetch(statusEndpoint, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin'
        });
        if (resp.status === 401 || resp.status === 403 || resp.status === 404 || resp.status === 419) {
          clearInterval(pollInterval);
          return;
        }
        if (!resp.ok) return;
        const data = await resp.json();
        if (data && data.is_paid) {
          clearInterval(pollInterval);
          if (statusBanner) {
            statusBanner.className = 'alert alert-success border py-2 px-3 small text-start mb-3 fw-semibold';
            statusBanner.innerHTML = '<i class="fas fa-check-circle me-1"></i> Pembayaran diterima! Memuat ulang halaman...';
          }
          setTimeout(function() { window.location.reload(); }, 600);
        }
      } catch (e) {
        // Ignore transient network hiccup and retry on next tick
      }
    }, 4000);

    window.addEventListener('beforeunload', function() {
      clearInterval(pollInterval);
    });
    @endif
  })();
</script>
@endpush
@endsection

