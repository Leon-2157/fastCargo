@extends('layouts.dashboard')

@section('title', 'WhatsApp Gateway (waG Station)')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">WhatsApp Gateway Station (waG)</h3>
    <p class="text-muted small mb-0">WhatsApp Go gateway station (<code>{{ $gatewayBaseUrl }}</code>) for automated consignment alerts &amp; tracking bot replies.</p>
  </div>
  <div id="wag-status-badge">
    @if(!($gatewayStatus['reachable'] ?? false))
      <span class="badge bg-danger px-3 py-2 fs-6"><i class="fas fa-plug me-1"></i> Gateway Unreachable ({{ $gatewayStatus['status'] ?? 'OFFLINE' }})</span>
    @elseif($isConnected)
      <span class="badge bg-success px-3 py-2 fs-6"><i class="fas fa-check-circle me-1"></i> Gateway Connected</span>
    @else
      <span class="badge bg-warning text-dark px-3 py-2 fs-6"><i class="fas fa-exclamation-triangle me-1"></i> Disconnected / Pairing Needed</span>
    @endif
  </div>
</div>

@if($errors->any())
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong>Validation failed:</strong> {{ $errors->first() }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="row">
  <!-- Gateway Health & Pairing Status Card -->
  <div class="col-lg-5 mb-4">
    <div class="card shadow text-center h-100">
      <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-success"><i class="fab fa-whatsapp me-1"></i> Gateway Pairing &amp; Status</h6>
        <span id="wag-service-badge" class="badge bg-light text-dark border small">{{ $gatewayStatus['service'] ?? 'waG' }}</span>
      </div>
      <div id="wag-pairing-panel" class="card-body p-4 d-flex flex-column justify-content-center align-items-center">
        @if($qrSvg)
          <div class="p-3 border rounded bg-light mb-3 d-inline-block">
            {!! $qrSvg !!}
          </div>
          <h6 class="fw-bold mb-1">Scan QR to Link Device</h6>
          <p class="text-muted small mb-3">Open WhatsApp &gt; Linked Devices &gt; Link a Device. Page updates automatically once scanned.</p>
          <a href="{{ route('admin.wa.qr', [], false) }}" target="_blank" class="btn btn-outline-success btn-sm">
            <i class="fas fa-external-link-alt me-1"></i> Open Raw QR SVG
          </a>
        @elseif($isConnected)
          <div class="text-success mb-3"><i class="fas fa-check-circle fa-4x"></i></div>
          <h6 class="fw-bold mb-1">Active Session Ready</h6>
          @if(!empty($gatewayStatus['user']['phone']))
            <p class="small fw-semibold text-dark mb-1">
              {{ $gatewayStatus['user']['name'] ?? 'Connected Device' }} (<code>+{{ $gatewayStatus['user']['phone'] }}</code>)
            </p>
          @endif
          <p class="text-muted small mb-0">
            Status: <strong>{{ $gatewayStatus['status'] ?? 'CONNECTED' }}</strong>. Outgoing booking notifications and WA bot tracking replies are active.
          </p>
        @elseif(!($gatewayStatus['reachable'] ?? false))
          <div class="text-danger mb-3"><i class="fas fa-server fa-4x"></i></div>
          <h6 class="fw-bold mb-1">waG Service Offline / Unreachable</h6>
          <p class="text-muted small mb-0">
            Could not reach <code>{{ $gatewayBaseUrl }}</code>. Start the Go <code>wa-gateway</code> service or verify <code>WAG_BASE_URL</code>.
          </p>
        @else
          <div class="spinner-border text-warning mb-3" role="status"><span class="visually-hidden">Loading...</span></div>
          <h6 class="fw-bold mb-1">Waiting for Fresh Pairing QR</h6>
          <p class="text-muted small mb-0">
            Gateway is reachable (<code>{{ $gatewayStatus['status'] ?? 'DISCONNECTED' }}</code>). Checking automatically for an updated QR code...
          </p>
        @endif
      </div>
      <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2 px-3">
        <span class="small text-muted"><i class="fas fa-sync-alt me-1"></i> <span id="wag-poll-note">Auto-sync active</span></span>
        <form method="POST" action="{{ route('admin.wa.reset', [], false) }}">
          @csrf
          <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-redo me-1"></i> Reset Session / New QR
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Test Dispatch Form & Automated Triggers Info -->
  <div class="col-lg-7 mb-4">
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-paper-plane me-1"></i> Test Dispatch Notification</h6>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin.wa.test', [], false) }}">
          @csrf

          <div class="mb-3">
            <label class="form-label small fw-bold text-muted" for="wa_test_phone">Destination Mobile Number (E.164 or 08xx)</label>
            <input id="wa_test_phone" type="text" name="phone" autocomplete="tel" value="{{ old('phone', '+62811000003') }}" class="form-control" placeholder="+628123456789" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-muted" for="wa_test_message">Dispatch Message Template</label>
            <textarea id="wa_test_message" name="message" rows="4" class="form-control" required>{{ old('message', '[FastCargo Alert] Your consignment RPN-20261106-001 has cleared customs at Tanjung Priok Hub and is currently SAILING aboard MV Nusantara Express to Singapore. Real-time telemetry: '.rtrim(config('app.portal_urls.landing', 'http://fastcargo.local:8080'), '/').'/track/RPN-20261106-001') }}</textarea>
          </div>

          <button type="submit" class="btn btn-success shadow">
            <i class="fas fa-paper-plane me-1"></i> Send Test Message
          </button>
        </form>
      </div>
    </div>

    <div class="card shadow border-start-info">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-bell me-1"></i> Automated Notification &amp; Bot Flows</h6>
      </div>
      <div class="card-body small text-muted">
        <p>FastCargo dispatches queued WhatsApp alerts (via Redis worker) on:</p>
        <ul class="ps-3 mb-2">
          <li class="mb-1"><strong>BOOKED / APPROVED:</strong> Consignment booking confirmation &amp; digital invoice reference.</li>
          <li class="mb-1"><strong>GATE_IN / SEALED:</strong> Container manifest seal confirmation &amp; terminal intake.</li>
          <li class="mb-1"><strong>MILESTONES (SAILING / ARRIVED / HOLD):</strong> Transit updates and compliance hold alerts.</li>
          <li><strong>DELIVERED:</strong> ePOD signed &amp; final delivery confirmation receipt.</li>
        </ul>
        <p class="mb-0"><strong>Inbound Bot Webhook:</strong> <code>POST /api/internal/wag/webhook</code> replies to customer <code>TRACK RPN-...</code> queries and general freight questions.</p>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const statusUrl = @json(route('admin.wa.status', [], false));
  const rawQrUrl = @json(route('admin.wa.qr', [], false));
  const baseUrl = @json($gatewayBaseUrl);
  const badgeEl = document.getElementById('wag-status-badge');
  const serviceEl = document.getElementById('wag-service-badge');
  const panelEl = document.getElementById('wag-pairing-panel');
  const pollNoteEl = document.getElementById('wag-poll-note');
  if (!badgeEl || !panelEl) return;

  let lastSignature = '';
  let timerId = null;

  const makeEl = (tag, className, text) => {
    const el = document.createElement(tag);
    if (className) el.className = className;
    if (text !== undefined && text !== null) el.textContent = text;
    return el;
  };

  const renderBadge = (data) => {
    badgeEl.replaceChildren();
    let cls = 'badge bg-warning text-dark px-3 py-2 fs-6';
    let iconCls = 'fas fa-exclamation-triangle me-1';
    let label = ' Disconnected / Pairing Needed';
    if (!data.reachable) {
      cls = 'badge bg-danger px-3 py-2 fs-6';
      iconCls = 'fas fa-plug me-1';
      label = ` Gateway Unreachable (${data.status || 'OFFLINE'})`;
    } else if (data.connected) {
      cls = 'badge bg-success px-3 py-2 fs-6';
      iconCls = 'fas fa-check-circle me-1';
      label = ' Gateway Connected';
    }
    const span = makeEl('span', cls);
    span.appendChild(makeEl('i', iconCls));
    span.appendChild(document.createTextNode(label));
    badgeEl.appendChild(span);
  };

  const renderPanel = (data) => {
    panelEl.replaceChildren();
    if (data.qr_svg) {
      const box = makeEl('div', 'p-3 border rounded bg-light mb-3 d-inline-block');
      const parsed = new DOMParser().parseFromString(data.qr_svg, 'image/svg+xml');
      const svg = parsed.documentElement && parsed.documentElement.nodeName.toLowerCase() === 'svg'
        ? document.importNode(parsed.documentElement, true)
        : null;
      if (svg) box.appendChild(svg);
      panelEl.appendChild(box);
      panelEl.appendChild(makeEl('h6', 'fw-bold mb-1', 'Scan QR to Link Device'));
      panelEl.appendChild(makeEl('p', 'text-muted small mb-3', 'Open WhatsApp > Linked Devices > Link a Device. Page updates automatically once scanned.'));
      const link = makeEl('a', 'btn btn-outline-success btn-sm');
      link.href = rawQrUrl;
      link.target = '_blank';
      link.appendChild(makeEl('i', 'fas fa-external-link-alt me-1'));
      link.appendChild(document.createTextNode(' Open Raw QR SVG'));
      panelEl.appendChild(link);
      return;
    }

    if (data.connected) {
      const iconWrap = makeEl('div', 'text-success mb-3');
      iconWrap.appendChild(makeEl('i', 'fas fa-check-circle fa-4x'));
      panelEl.appendChild(iconWrap);
      panelEl.appendChild(makeEl('h6', 'fw-bold mb-1', 'Active Session Ready'));
      if (data.user && data.user.phone) {
        const userLine = makeEl('p', 'small fw-semibold text-dark mb-1');
        userLine.appendChild(document.createTextNode(`${data.user.name || 'Connected Device'} (`));
        userLine.appendChild(makeEl('code', '', `+${data.user.phone}`));
        userLine.appendChild(document.createTextNode(')'));
        panelEl.appendChild(userLine);
      }
      const desc = makeEl('p', 'text-muted small mb-0');
      desc.appendChild(document.createTextNode('Status: '));
      desc.appendChild(makeEl('strong', '', data.status || 'CONNECTED'));
      desc.appendChild(document.createTextNode('. Outgoing booking notifications and WA bot tracking replies are active.'));
      panelEl.appendChild(desc);
      return;
    }

    if (!data.reachable) {
      const iconWrap = makeEl('div', 'text-danger mb-3');
      iconWrap.appendChild(makeEl('i', 'fas fa-server fa-4x'));
      panelEl.appendChild(iconWrap);
      panelEl.appendChild(makeEl('h6', 'fw-bold mb-1', 'waG Service Offline / Unreachable'));
      const desc = makeEl('p', 'text-muted small mb-0');
      desc.appendChild(document.createTextNode('Could not reach '));
      desc.appendChild(makeEl('code', '', baseUrl));
      desc.appendChild(document.createTextNode('. Start the Go wa-gateway service or verify WAG_BASE_URL.'));
      panelEl.appendChild(desc);
      return;
    }

    const spinner = makeEl('div', 'spinner-border text-warning mb-3');
    spinner.setAttribute('role', 'status');
    spinner.appendChild(makeEl('span', 'visually-hidden', 'Loading...'));
    panelEl.appendChild(spinner);
    panelEl.appendChild(makeEl('h6', 'fw-bold mb-1', 'Waiting for Fresh Pairing QR'));
    const desc = makeEl('p', 'text-muted small mb-0');
    desc.appendChild(document.createTextNode('Gateway is reachable ('));
    desc.appendChild(makeEl('code', '', data.status || 'DISCONNECTED'));
    desc.appendChild(document.createTextNode('). Checking automatically for an updated QR code...'));
    panelEl.appendChild(desc);
  };

  const poll = async () => {
    let nextDelay = 3000;
    try {
      const resp = await fetch(statusUrl, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        cache: 'no-store',
        credentials: 'same-origin'
      });
      if (resp.ok) {
        const data = await resp.json();
        const sig = JSON.stringify([data.reachable, data.connected, data.status, data.service, data.user && data.user.phone, data.qr_svg || '']);
        if (sig !== lastSignature) {
          lastSignature = sig;
          if (serviceEl) serviceEl.textContent = data.service || 'waG';
          renderBadge(data);
          renderPanel(data);
        }
        nextDelay = data.connected ? 10000 : 2500;
        if (pollNoteEl) {
          pollNoteEl.textContent = `Live sync (${data.status || 'unknown'})`;
        }
      }
    } catch (_) {
      nextDelay = 5000;
    }
    timerId = window.setTimeout(poll, nextDelay);
  };

  timerId = window.setTimeout(poll, 2500);
  window.addEventListener('beforeunload', () => {
    if (timerId !== null) window.clearTimeout(timerId);
  });
})();
</script>
@endpush
