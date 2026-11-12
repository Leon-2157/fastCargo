@extends('layouts.dashboard')

@section('title', 'Payment Webhook Simulator — FastCargo Admin')

@push('styles')
<style>
  .json-viewer-box {
    background: #0f172a;
    color: #38bdf8;
    font-family: 'SFMono-Regular', Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    padding: 14px;
    border-radius: 8px;
    border: 1px solid #1e293b;
    max-height: 280px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
  }
  .scenario-btn-group input:checked + label {
    font-weight: 700;
  }
</style>
@endpush

@section('content')
<div class="container-fluid">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h3 class="text-dark fw-bold mb-1">
        <i class="fas fa-satellite-dish text-primary me-2"></i>Payment Webhook Simulator
      </h3>
      <p class="text-muted small mb-0">Testbed console for simulating Dana Business QRIS Snap API callbacks and verifying Escrow Ledger funding.</p>
    </div>
    <div>
      <span class="badge bg-success px-3 py-2 text-white rounded-pill">
        <i class="fas fa-shield-alt me-1"></i> Sandbox Testbed Isolated
      </span>
    </div>
  </div>

  <div class="row g-4">
    <!-- Left Column: Controls & Dispatcher -->
    <div class="col-lg-5">
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
          <h6 class="m-0 fw-bold text-dark">
            <i class="fas fa-sliders-h text-primary me-2"></i>Callback Scenario Controls
          </h6>
        </div>
        <div class="card-body">
          <!-- 1-Click Generator -->
          <div class="p-3 bg-light rounded-3 mb-4 border">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="small fw-bold text-dark">Quick Test Setup</span>
              <span class="badge bg-primary">Instant</span>
            </div>
            <p class="text-muted small mb-2">Need a test invoice? Generate an isolated sandbox transaction in 1 click without manual booking:</p>
            <button type="button" id="btnGenSandbox" class="btn btn-outline-primary btn-sm w-100 fw-bold">
              <i class="fas fa-magic me-1"></i> Generate Sandbox Test Invoice
            </button>
          </div>

          <form id="webhookForm">
            @csrf
            <!-- Existing / Generated Transaction Selector -->
            <div class="mb-3">
              <label class="form-label small text-muted fw-bold" for="selectTransaction">Select Pending Transaction</label>
              <select class="form-select form-select-sm" id="selectTransaction">
                <option value="">-- Choose Existing Transaction or Enter Manually --</option>
                @foreach($transactions as $tx)
                  <option value="{{ $tx->transaction_ref }}" data-amount="{{ $tx->amount }}" data-invoice="{{ $tx->invoice?->invoice_number }}">
                    {{ $tx->transaction_ref }} ({{ $tx->invoice?->invoice_number ?? 'No INV' }}) — IDR {{ number_format($tx->amount) }} [{{ $tx->payment_status }}]
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label small text-muted fw-bold" for="partnerRefInput">Partner Reference No (partnerReferenceNo)</label>
              <input type="text" class="form-control form-control-sm font-monospace" id="partnerRefInput" name="partnerReferenceNo" placeholder="SANDBOX-DANA-XXXX" required>
            </div>

            <div class="mb-3">
              <label class="form-label small text-muted fw-bold">Callback Status Scenario</label>
              <div class="scenario-btn-group d-flex flex-wrap gap-2">
                <input type="radio" class="btn-check" name="scenario" id="scenSettlement" value="SETTLEMENT" checked>
                <label class="btn btn-sm btn-outline-success" for="scenSettlement">
                  <i class="fas fa-check-circle me-1"></i> SETTLEMENT (00)
                </label>

                <input type="radio" class="btn-check" name="scenario" id="scenExpired" value="EXPIRED">
                <label class="btn btn-sm btn-outline-secondary" for="scenExpired">
                  <i class="fas fa-clock me-1"></i> EXPIRED (01)
                </label>

                <input type="radio" class="btn-check" name="scenario" id="scenFailed" value="FAILED">
                <label class="btn btn-sm btn-outline-danger" for="scenFailed">
                  <i class="fas fa-times-circle me-1"></i> FAILED (02)
                </label>

                <input type="radio" class="btn-check" name="scenario" id="scenCancelled" value="CANCELLED">
                <label class="btn btn-sm btn-outline-dark" for="scenCancelled">
                  <i class="fas fa-ban me-1"></i> CANCELLED (03)
                </label>
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label small text-muted fw-bold" for="amountInput">Transaction Amount (IDR)</label>
              <input type="number" class="form-control form-control-sm" id="amountInput" name="amount" value="6500000" step="1000">
            </div>

            <button type="submit" id="btnDispatch" class="btn btn-primary d-block w-100 py-2 fw-bold">
              <i class="fas fa-paper-plane me-1"></i> Dispatch Simulated Webhook
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Right Column: Live Inspector & Ledger State -->
    <div class="col-lg-7">
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
          <h6 class="m-0 fw-bold text-dark">
            <i class="fas fa-search-dollar text-primary me-2"></i>Live Inspection &amp; Ledger Audit
          </h6>
          <span id="responseStatusBadge" class="badge bg-secondary px-3 py-1">Ready</span>
        </div>
        <div class="card-body">
          <!-- Ledger State Indicators -->
          <div class="row g-2 mb-3">
            <div class="col-sm-4">
              <div class="p-2 border rounded text-center">
                <div class="text-muted small" style="font-size: 11px;">TX Status</div>
                <div class="fw-bold mt-1" id="stateTxStatus">—</div>
              </div>
            </div>
            <div class="col-sm-4">
              <div class="p-2 border rounded text-center">
                <div class="text-muted small" style="font-size: 11px;">Invoice Status</div>
                <div class="fw-bold mt-1" id="stateInvoiceStatus">—</div>
              </div>
            </div>
            <div class="col-sm-4">
              <div class="p-2 border rounded text-center">
                <div class="text-muted small" style="font-size: 11px;">Escrow Ledger</div>
                <div class="fw-bold mt-1" id="stateEscrowStatus">—</div>
              </div>
            </div>
          </div>

          <!-- JSON Payloads -->
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label small text-muted fw-bold mb-0">Raw Dispatch Payload (Dana Snap Standard)</label>
              <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small" id="btnCopyReq">Copy JSON</button>
            </div>
            <pre class="json-viewer-box mb-0" id="jsonRequest">// Webhook payload will appear here upon dispatch...</pre>
          </div>

          <div>
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label small text-muted fw-bold mb-0">FastCargo Server Response Body</label>
              <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small" id="btnCopyRes">Copy JSON</button>
            </div>
            <pre class="json-viewer-box mb-0" id="jsonResponse" style="color: #4ade80;">// Server response will appear here...</pre>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bottom Section: Immutable Governance Log -->
  <div class="card shadow-sm border-0 rounded-3 mt-2">
    <div class="card-header bg-white py-3 border-bottom">
      <h6 class="m-0 fw-bold text-dark">
        <i class="fas fa-history text-secondary me-2"></i>Simulator Audit History
      </h6>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-striped align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th>Timestamp</th>
              <th>Action</th>
              <th>Target ID</th>
              <th>Actor</th>
              <th>IP Address</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recentLogs as $log)
              <tr>
                <td>{{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}</td>
                <td><span class="badge bg-light text-dark border">{{ $log->action }}</span></td>
                <td class="font-monospace text-primary">{{ $log->target_id ?? 'N/A' }}</td>
                <td>{{ $log->actor_type }}</td>
                <td>{{ $log->ip_address }}</td>
                <td class="font-monospace text-muted text-xs" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                  {{ $log->after }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted py-3">No simulation logs recorded yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const btnGen = document.getElementById('btnGenSandbox');
    const selectTx = document.getElementById('selectTransaction');
    const partnerRefInput = document.getElementById('partnerRefInput');
    const amountInput = document.getElementById('amountInput');
    const form = document.getElementById('webhookForm');
    const btnDispatch = document.getElementById('btnDispatch');
    const statusBadge = document.getElementById('responseStatusBadge');

    const stateTx = document.getElementById('stateTxStatus');
    const stateInvoice = document.getElementById('stateInvoiceStatus');
    const stateEscrow = document.getElementById('stateEscrowStatus');

    const jsonReq = document.getElementById('jsonRequest');
    const jsonRes = document.getElementById('jsonResponse');

    // Handle 1-Click Sandbox Generator
    btnGen.addEventListener('click', function () {
      btnGen.disabled = true;
      btnGen.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';

      fetch('{{ route("admin.simulator.generate", [], false) }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json'
        }
      })
      .then(res => res.json())
      .then(data => {
        btnGen.disabled = false;
        btnGen.innerHTML = '<i class="fas fa-magic me-1"></i> Generate Sandbox Test Invoice';

        if (data.success && data.transaction) {
          partnerRefInput.value = data.transaction.transaction_ref;
          amountInput.value = data.transaction.amount;

          // Add to dropdown and select
          const opt = document.createElement('option');
          opt.value = data.transaction.transaction_ref;
          opt.dataset.amount = data.transaction.amount;
          opt.dataset.invoice = data.transaction.invoice_number;
          opt.textContent = `${data.transaction.transaction_ref} (${data.transaction.invoice_number}) — IDR ${data.transaction.amount} [PENDING]`;
          selectTx.prepend(opt);
          selectTx.value = data.transaction.transaction_ref;

          stateTx.innerHTML = '<span class="badge bg-warning text-dark">PENDING</span>';
          stateInvoice.innerHTML = '<span class="badge bg-warning text-dark">PENDING</span>';
          stateEscrow.innerHTML = '<span class="badge bg-secondary">NOT_FUNDED</span>';
        }
      })
      .catch(err => {
        btnGen.disabled = false;
        btnGen.innerHTML = '<i class="fas fa-magic me-1"></i> Generate Sandbox Test Invoice';
        alert('Failed to generate sandbox transaction: ' + err);
      });
    });

    // Handle Dropdown Selection
    selectTx.addEventListener('change', function () {
      const selected = selectTx.options[selectTx.selectedIndex];
      if (selected && selected.value) {
        partnerRefInput.value = selected.value;
        if (selected.dataset.amount) {
          amountInput.value = selected.dataset.amount;
        }
      }
    });

    // Handle Webhook Dispatch
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const ref = partnerRefInput.value.trim();
      const amount = amountInput.value;
      const scenario = document.querySelector('input[name="scenario"]:checked').value;

      if (!ref) {
        alert('Please specify partnerReferenceNo.');
        return;
      }

      btnDispatch.disabled = true;
      btnDispatch.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Dispatching Webhook...';
      statusBadge.className = 'badge bg-secondary px-3 py-1';
      statusBadge.textContent = 'Sending...';

      fetch('{{ route("admin.simulator.fire", [], false) }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          partnerReferenceNo: ref,
          scenario: scenario,
          amount: amount
        })
      })
      .then(res => res.json())
      .then(data => {
        btnDispatch.disabled = false;
        btnDispatch.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Dispatch Simulated Webhook';

        if (data.http_status === 200) {
          statusBadge.className = 'badge bg-success px-3 py-1';
          statusBadge.textContent = `HTTP ${data.http_status} OK`;
        } else {
          statusBadge.className = 'badge bg-danger px-3 py-1';
          statusBadge.textContent = `HTTP ${data.http_status} Error`;
        }

        // Render formatted JSON
        jsonReq.textContent = JSON.stringify(data.request_payload, null, 2);
        jsonRes.textContent = JSON.stringify(data.response_payload, null, 2);

        // Render Ledger Status
        if (data.ledger_state) {
          stateTx.innerHTML = `<span class="badge ${data.ledger_state.transaction_status === 'SETTLEMENT' ? 'bg-success' : 'bg-secondary'}">${data.ledger_state.transaction_status}</span>`;
          stateInvoice.innerHTML = `<span class="badge ${data.ledger_state.invoice_status === 'PAID_SETTLED' ? 'bg-success' : 'bg-secondary'}">${data.ledger_state.invoice_status}</span>`;
          stateEscrow.innerHTML = `<span class="badge ${data.ledger_state.escrow_status === 'HELD_IN_ESCROW' ? 'bg-success' : 'bg-secondary'}">${data.ledger_state.escrow_status}</span>`;
        }
      })
      .catch(err => {
        btnDispatch.disabled = false;
        btnDispatch.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Dispatch Simulated Webhook';
        statusBadge.className = 'badge bg-danger px-3 py-1';
        statusBadge.textContent = 'Network Error';
        jsonRes.textContent = String(err);
      });
    });

    // Copy JSON helpers
    document.getElementById('btnCopyReq').addEventListener('click', () => {
      navigator.clipboard.writeText(jsonReq.textContent);
      alert('Request payload copied to clipboard.');
    });
    document.getElementById('btnCopyRes').addEventListener('click', () => {
      navigator.clipboard.writeText(jsonRes.textContent);
      alert('Response payload copied to clipboard.');
    });
  });
</script>
@endpush

