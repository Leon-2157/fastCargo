@extends('layouts.dashboard')

@section('title', 'Electronic Proof of Delivery (ePOD)')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('operator.bookings.show', $booking->id) }}" class="btn btn-outline-secondary btn-sm mb-2">
      <i class="fas fa-arrow-left me-1"></i> Back to Consignment
    </a>
    <h3 class="text-dark mb-0 fw-bold">Electronic Proof of Delivery (ePOD)</h3>
    <p class="text-muted small mb-0">Consignment: <strong>{{ $booking->tracking_number }}</strong> &bull; Shipper: {{ $booking->shipper?->name }}</p>
  </div>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card shadow mb-4 border-start-success">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-signature me-1"></i> Final Mile Delivery Handover</h6>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="{{ route('operator.epod.store', $booking->id) }}" id="epodForm">
          @csrf

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold text-secondary">Authorized Recipient Name</label>
              <input type="text" name="recipient_name" class="form-control @error('recipient_name') is-invalid @enderror" placeholder="Full name of receiver" required>
              @error('recipient_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold text-secondary">Recipient Contact Phone</label>
              <input type="text" name="recipient_phone" class="form-control @error('recipient_phone') is-invalid @enderror" placeholder="+628123456789" required>
              @error('recipient_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold text-secondary">Delivery Latitude</label>
              <input type="number" step="0.0000001" name="latitude" id="epodLat" value="{{ $booking->route?->destinationPort?->latitude ?? 1.2654 }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold text-secondary">Delivery Longitude</label>
              <input type="number" step="0.0000001" name="longitude" id="epodLng" value="{{ $booking->route?->destinationPort?->longitude ?? 103.8198 }}" class="form-control" required>
            </div>
          </div>

          <!-- HTML5 Signature Canvas -->
          <div class="mb-4">
            <label class="form-label fw-semibold text-secondary d-flex justify-content-between">
              <span>Recipient Electronic Signature</span>
              <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="clearSignature()">Clear Pad</button>
            </label>
            <div class="border rounded bg-light text-center" style="position: relative;">
              <canvas id="signatureCanvas" width="600" height="180" style="width: 100%; height: 180px; cursor: crosshair; touch-action: none; background: #fff;"></canvas>
            </div>
            <input type="hidden" name="signature_data" id="signatureData">
            <small class="text-muted">Sign with finger or stylus on touchscreen devices, or mouse pointer on desktop.</small>
          </div>

          <button type="submit" class="btn btn-success btn-lg px-4 shadow">
            <i class="fas fa-check-double me-1"></i> Confirm Delivery &amp; Release Escrow
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow border-start-info">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-shield-alt me-1"></i> Compliance Notice</h6>
      </div>
      <div class="card-body small text-muted">
        <p>Signing and confirming this ePOD executes the final operational milestone:</p>
        <ul class="ps-3 mb-0">
          <li class="mb-2">Booking status transitions to <strong>DELIVERED</strong>.</li>
          <li class="mb-2">Admin Escrow Ledger milestone 3 (30%) is unlocked and released to operator account.</li>
          <li>Delivery confirmation timestamp and GPS geofence are locked immutably into the audit trail.</li>
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  const canvas = document.getElementById('signatureCanvas');
  const ctx = canvas.getContext('2d');
  let drawing = false;

  ctx.strokeStyle = '#224abe';
  ctx.lineWidth = 2;
  ctx.lineCap = 'round';

  function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return {
      x: (clientX - rect.left) * (canvas.width / rect.width),
      y: (clientY - rect.top) * (canvas.height / rect.height)
    };
  }

  function startDraw(e) {
    drawing = true;
    const pos = getPos(e);
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y);
    e.preventDefault();
  }

  function draw(e) {
    if (!drawing) return;
    const pos = getPos(e);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
    e.preventDefault();
  }

  function stopDraw() {
    drawing = false;
    document.getElementById('signatureData').value = canvas.toDataURL();
  }

  canvas.addEventListener('mousedown', startDraw);
  canvas.addEventListener('mousemove', draw);
  canvas.addEventListener('mouseup', stopDraw);

  canvas.addEventListener('touchstart', startDraw);
  canvas.addEventListener('touchmove', draw);
  canvas.addEventListener('touchend', stopDraw);

  function clearSignature() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    document.getElementById('signatureData').value = '';
  }

  // Geolocation auto-fill if permitted
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      document.getElementById('epodLat').value = pos.coords.latitude.toFixed(7);
      document.getElementById('epodLng').value = pos.coords.longitude.toFixed(7);
    }, null, { timeout: 5000 });
  }
</script>
@endpush

